<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ChannelDirectory;
use Modules\Core\Contracts\CreatesPickingList;
use Modules\Core\Contracts\CreditGuard;
use Modules\Core\Contracts\StockLedger;
use Modules\Core\Contracts\WarehouseDirectory;
use Modules\Core\Domain\Events\SubOrderConfirmed;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Ordering\Domain\Enums\OrderSource;
use Modules\Ordering\Domain\Enums\SubOrderStatus;
use Modules\Ordering\Domain\Models\SubOrder;
use Modules\Ordering\Domain\Models\SubOrderEvent;
use Modules\Ordering\Domain\SubOrderStateMachine;

final class ConfirmSubOrder
{
    public function __construct(
        private readonly SubOrderStateMachine $machine,
        private readonly CreditGuard $credit,
        private readonly StockLedger $ledger,
        private readonly WarehouseDirectory $warehouses,
        private readonly CreatesPickingList $picking,
        private readonly ChannelDirectory $channels,
        private readonly AssignSubOrders $assign,
    ) {}

    /**
     * @param  'channel'|'warehouse'  $side
     * @return array{status: string, picking_list_id: int|null}
     */
    public function __invoke(object $actor, int $id, string $side = 'channel'): array
    {
        $sub = SubOrder::query()->with('lines')->find($id);
        if ($sub === null) {
            throw new DomainException(__('ordering.not_found'), 'not_found', 404);
        }

        $this->assertApproverSide((int) $sub->channel_id, $side);
        $this->machine->assert($sub->status, 'confirm');
        $this->credit->assertWithinLimit((int) $sub->retailer_id, (int) $sub->channel_id, (int) $sub->total);

        $warehouseId = $this->warehouses->defaultIdForChannel((int) $sub->channel_id);
        if ($warehouseId === null) {
            throw new DomainException(__('inventory.warehouse_not_found'), 'not_found', 404);
        }

        $lines = $sub->lines->map(fn ($l) => [
            'product_id' => (int) $l->product_id,
            'variant_id' => $l->variant_id ? (int) $l->variant_id : null,
            'qty' => (int) $l->qty,
        ])->all();

        DB::transaction(function () use ($sub, $actor, $warehouseId, $lines): void {
            foreach ($lines as $line) {
                $this->ledger->reserve(
                    (int) $sub->id,
                    $warehouseId,
                    $line['product_id'],
                    $line['variant_id'],
                    $line['qty'],
                    $actor,
                );
            }

            $sub->status = SubOrderStatus::Confirmed;
            $sub->save();
            SubOrderEvent::query()->create([
                'sub_order_id' => $sub->id,
                'stage' => SubOrderStatus::Confirmed->value,
                'at' => now(),
                'actor_type' => $actor::class,
                'actor_id' => $actor->getAuthIdentifier(),
            ]);

            event(new SubOrderConfirmed((int) $sub->id, (int) $sub->channel_id, $warehouseId, $lines));
        });

        $sub->refresh();
        if ($sub->source === OrderSource::RepApp && $sub->rep_id) {
            $this->assign->assignExistingRep($actor, (int) $sub->id, (int) $sub->rep_id);
            $sub->refresh();
        }

        return [
            'status' => $sub->status->value,
            'picking_list_id' => $this->picking->idForSubOrder((int) $sub->id),
        ];
    }

    private function assertApproverSide(int $channelId, string $side): void
    {
        $settings = $this->channels->settings($channelId);
        $approver = (string) ($settings['order_approver'] ?? 'channel');
        if (! in_array($approver, ['channel', 'warehouse'], true)) {
            $approver = 'channel';
        }
        if ($approver !== $side) {
            throw new DomainException(__('ordering.illegal_transition'), 'illegal_transition', 409);
        }
    }
}
