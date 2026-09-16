<?php

declare(strict_types=1);

namespace Modules\Returns\Application;

use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Contracts\StockLedger;
use Modules\Core\Contracts\SubOrderLifecycle;
use Modules\Core\Contracts\WarehouseDirectory;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Events\ReturnDecided;
use Modules\Core\Domain\Events\ReturnRequested;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Returns\Domain\Models\ReturnDecision as DecisionRow;
use Modules\Returns\Domain\Models\ReturnLine;
use Modules\Returns\Domain\Models\ReturnRequest;

final class ReturnsWorkspace
{
    public function __construct(
        private readonly SubOrderLifecycle $orders,
        private readonly StockLedger $ledger,
        private readonly WarehouseDirectory $warehouses,
        private readonly RecordsAudit $audit,
        private readonly RetailerShoppingContext $shopping,
    ) {}

    /**
     * @param  array{sub_order_id: int, type: string, lines: list<array{line_id: int, qty: int, reason: string, photos?: array}>}  $data
     * @return array{request_no: string, status: string}
     */
    public function create(object $actor, array $data): array
    {
        $header = $this->orders->header((int) $data['sub_order_id']);
        if ($header === null) {
            throw new DomainException(__('returns.not_found'), 'not_found', 404);
        }
        $row = ReturnRequest::query()->create([
            'channel_id' => $header['channel_id'],
            'sub_order_id' => $data['sub_order_id'],
            'zone_id' => $header['zone_id'],
            'rep_id' => $header['rep_id'],
            'requester_type' => $actor::class,
            'requester_id' => $actor->getAuthIdentifier(),
            'type' => $data['type'],
            'status' => 'pending',
            'request_no' => 'RR-tmp',
        ]);
        $row->forceFill(['request_no' => 'RR-'.$row->id])->save();
        foreach ($data['lines'] as $line) {
            ReturnLine::query()->create([
                'return_request_id' => $row->id,
                'line_id' => $line['line_id'],
                'qty' => $line['qty'],
                'reason' => $line['reason'],
                'photos' => $line['photos'] ?? [],
            ]);
        }
        event(new ReturnRequested((int) $row->id, (int) $data['sub_order_id'], (int) $header['channel_id']));

        return ['request_no' => $row->request_no, 'status' => 'pending'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForRetailer(object $user): array
    {
        $ctx = $this->shopping->for($user);
        $retailerId = (int) $ctx['retailer_id'];

        return ReturnRequest::query()->orderByDesc('id')->get()
            ->filter(function (ReturnRequest $r) use ($retailerId): bool {
                $h = $this->orders->header((int) $r->sub_order_id);

                return $h !== null && (int) $h['retailer_id'] === $retailerId;
            })
            ->map(fn (ReturnRequest $r) => [
                'request_no' => $r->request_no,
                'type' => $r->type,
                'status' => $r->status,
            ])->values()->all();
    }

    /**
     * @return array{status: string}
     */
    public function decide(int $id, array $data, object $actor): array
    {
        $row = ReturnRequest::query()->find($id);
        if ($row === null) {
            throw new DomainException(__('returns.not_found'), 'not_found', 404);
        }
        if ($row->status !== 'pending') {
            throw DomainException::of(ErrorCode::IllegalTransition);
        }
        $row->status = $data['decision'] === 'approve' ? 'approved' : 'rejected';
        $row->save();
        DecisionRow::query()->create([
            'return_request_id' => $row->id,
            'decision' => $data['decision'],
            'reason' => $data['reason'] ?? null,
            'actor_type' => $actor::class,
            'actor_id' => $actor->getAuthIdentifier(),
        ]);
        $this->audit->record('returns.decide', $actor, 'return_request', (int) $row->id, $data, (int) $row->channel_id);
        event(new ReturnDecided((int) $row->id, (int) $row->sub_order_id, $data['decision']));

        return ['status' => $row->status];
    }

    /**
     * @param  list<array{line_id: int, condition: string}>  $lines
     * @return array{status: string}
     */
    public function sort(int $id, array $lines, object $actor): array
    {
        $row = ReturnRequest::query()->with('lines')->find($id);
        if ($row === null || $row->status !== 'approved') {
            throw new DomainException(__('returns.not_found'), 'not_found', 404);
        }
        $warehouseId = $this->warehouses->defaultIdForChannel((int) $row->channel_id);
        if ($warehouseId === null) {
            throw new DomainException(__('inventory.warehouse_not_found'), 'not_found', 404);
        }
        foreach ($lines as $patch) {
            $line = $row->lines->firstWhere('id', (int) $patch['line_id']);
            if ($line === null) {
                continue;
            }
            $line->condition = $patch['condition'];
            $line->save();
            $header = $this->orders->header((int) $row->sub_order_id);
            $orderLines = collect($this->orders->lines((int) $row->sub_order_id))->firstWhere('id', (int) $line->line_id);
            $this->ledger->returnIn(
                $warehouseId,
                (int) ($orderLines['product_id'] ?? 0),
                $orderLines['variant_id'] ?? null,
                (int) $line->qty,
                $patch['condition'],
                $actor,
                'return_request',
                (int) $row->id,
            );
        }
        $row->status = 'sorted';
        $row->save();

        return ['status' => 'sorted'];
    }
}
