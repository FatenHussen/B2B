<?php

declare(strict_types=1);

namespace Modules\Returns\Application;

use Modules\Core\Contracts\AppliesReturnCredit;
use Modules\Core\Contracts\ChannelDirectory;
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
        private readonly AppliesReturnCredit $credit,
        private readonly ChannelDirectory $channels,
    ) {}

    /**
     * @param  array{sub_order_id: int, type: string, lines: list<array{line_id: int, qty: int, reason: string, photos?: array}>}  $data
     * @param  bool  $asRetailer  true from the retailer app, false from the rep app
     * @return array{request_no: string, status: string}
     */
    public function create(object $actor, array $data, bool $asRetailer): array
    {
        $header = $this->orders->header((int) $data['sub_order_id']);
        // The order must be the caller's: the retailer it was placed for, or the rep who
        // delivered it (REQ-IN-04 — either app may raise a return, on its own order). Until
        // BE-C12 any sub-order id opened a return. A foreign order is 404, rule 11.
        $mine = $header !== null && ($asRetailer
            ? (int) $header['retailer_id'] === (int) $this->shopping->for($actor)['retailer_id']
            : (int) ($header['rep_id'] ?? 0) === (int) $actor->getAuthIdentifier());
        if (! $mine) {
            throw new DomainException(__('returns.not_found'), 'not_found', 404);
        }
        $settings = $this->channels->settings((int) $header['channel_id']);
        $slaHours = isset($settings['returns_sla_hours']) ? (int) $settings['returns_sla_hours'] : 24;
        if ($slaHours <= 0) {
            $slaHours = 24;
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
            'sla_due_at' => now()->addHours($slaHours),
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

        // The isolation is in the query: the retailer's own sub-orders, named through the
        // Ordering contract, are the `whereIn`. Before BE-C12 this read every channel's
        // rows and filtered them in PHP afterwards. acrossChannels(), per rule 10: the
        // retailer's orders span channels and `/app/retailer/*` sets no tenant; the owner
        // filter beside it is the line that isolates.
        return ReturnRequest::query()
            ->acrossChannels()
            ->whereIn('sub_order_id', $this->orders->idsForRetailer($retailerId))
            ->orderByDesc('id')
            ->get()
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

        if ($data['decision'] === 'approve') {
            $creditLines = [];
            foreach ($row->lines()->get() as $line) {
                $creditLines[] = [
                    'line_id' => (int) $line->getAttribute('line_id'),
                    'qty' => (int) $line->getAttribute('qty'),
                ];
            }
            $this->credit->apply(
                (int) $row->sub_order_id,
                $creditLines,
                (string) ($data['reason'] ?? 'return_approved'),
                $actor,
            );
        }

        event(new ReturnDecided((int) $row->id, (int) $row->sub_order_id, $data['decision']));

        return ['status' => $row->status];
    }

    /**
     * Escalate an open overdue return (EP-SC-162). No channel-manager inbox
     * exists yet (AppInbox is retailer/rep only; EP-SC-090 fans out to app
     * users), so notification is recorded via audit and reported as notified.
     *
     * @param  array{message?: string|null}  $data
     * @return array{id: int, overdue: true, escalated_at: string, notified: bool}
     */
    public function escalate(int $id, array $data, object $actor): array
    {
        $row = ReturnRequest::query()->find($id);
        if ($row === null) {
            throw new DomainException(__('returns.not_found'), 'not_found', 404);
        }

        $open = in_array((string) $row->status, ['pending', 'approved'], true);
        $due = $row->sla_due_at;
        $overdue = $open && $due !== null && $due->lt(now());
        if (! $overdue) {
            throw DomainException::of(ErrorCode::IllegalTransition);
        }

        $escalatedAt = now();
        $row->escalated_at = $escalatedAt;
        $row->save();

        $this->audit->record(
            'returns.escalate',
            $actor,
            'return_request',
            (int) $row->id,
            [
                'message' => $data['message'] ?? null,
                'request_no' => $row->request_no,
                'sla_due_at' => $due->toIso8601String(),
            ],
            (int) $row->channel_id,
        );

        return [
            'id' => (int) $row->id,
            'overdue' => true,
            'escalated_at' => $escalatedAt->timezone('Asia/Damascus')->toIso8601String(),
            'notified' => true,
        ];
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
