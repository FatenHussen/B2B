<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Actions;

use Modules\Core\Contracts\PricingEngine;
use Modules\Core\Contracts\StockLedger;
use Modules\Core\Contracts\WarehouseDirectory;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Ordering\Domain\Models\SubOrder;
use Modules\Ordering\Domain\SubOrderStateMachine;

final class EditSubOrderLines
{
    public function __construct(
        private readonly SubOrderStateMachine $machine,
        private readonly PricingEngine $pricing,
        private readonly StockLedger $ledger,
        private readonly WarehouseDirectory $warehouses,
    ) {}

    /**
     * @param  array{changes: list<array{line_id: int, qty: int, removed?: bool}>, reason: string}  $data
     * @return array{status: string, total: int}
     */
    public function __invoke(object $actor, int $id, array $data): array
    {
        $sub = SubOrder::query()->with('lines')->find($id);
        if ($sub === null) {
            throw new DomainException(__('ordering.not_found'), 'not_found', 404);
        }
        $this->machine->assert($sub->status, 'edit_lines');

        foreach ($data['changes'] as $change) {
            $line = $sub->lines->firstWhere('id', (int) $change['line_id']);
            if ($line === null) {
                continue;
            }
            if (! empty($change['removed'])) {
                $line->delete();

                continue;
            }
            $line->qty = (int) $change['qty'];
            $line->save();
        }

        $sub->refresh()->load('lines');
        $quoteLines = [];
        foreach ($sub->lines as $line) {
            $quoteLines[] = [
                'product_id' => (int) $line->product_id,
                'variant_id' => $line->variant_id ? (int) $line->variant_id : null,
                'qty' => (int) $line->qty,
            ];
        }
        $quote = $this->pricing->quote([
            'lines' => $quoteLines,
            'zone_id' => (int) $sub->zone_id,
            'retailer_id' => (int) $sub->retailer_id,
            'channel_id' => (int) $sub->channel_id,
        ]);

        $total = 0;
        $discount = 0;
        foreach ($sub->lines as $i => $line) {
            $q = $quote['lines'][$i] ?? null;
            if ($q === null) {
                continue;
            }
            $line->forceFill([
                'unit_price' => (int) $q['unit_price'],
                'discount' => (int) ($q['discount'] ?? 0),
                'line_total' => (int) $q['line_total'],
                'applied_rule' => $q['applied_rule'] ?? null,
            ])->save();
            $total += (int) $q['line_total'];
            $discount += (int) ($q['discount'] ?? 0);
        }
        $sub->forceFill([
            'subtotal' => $total + $discount,
            'discount' => $discount,
            'total' => $total,
        ])->save();

        if ($sub->status->value !== 'pending') {
            $this->ledger->release((int) $sub->id, $actor);
            $warehouseId = $this->warehouses->defaultIdForChannel((int) $sub->channel_id);
            if ($warehouseId !== null) {
                foreach ($sub->lines as $line) {
                    $this->ledger->reserve(
                        (int) $sub->id,
                        $warehouseId,
                        (int) $line->product_id,
                        $line->variant_id ? (int) $line->variant_id : null,
                        (int) $line->qty,
                        $actor,
                    );
                }
            }
        }

        return ['status' => $sub->status->value, 'total' => (int) $sub->total];
    }
}
