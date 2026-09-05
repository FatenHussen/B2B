<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Queries;

use Modules\Core\Contracts\RetailerDirectory;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Ordering\Domain\Models\SubOrder;
use Modules\Ordering\Domain\SubOrderStateMachine;

final class ShowChannelSubOrder
{
    public function __construct(
        private readonly SubOrderStateMachine $machine,
        private readonly RetailerDirectory $retailers,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(object $user, int $id): array
    {
        $sub = SubOrder::query()->with(['lines', 'events'])->where('channel_id', Tenant::currentId())->find($id);
        if ($sub === null) {
            throw new DomainException(__('ordering.not_found'), 'not_found', 404);
        }
        $shop = $this->retailers->find((int) $sub->retailer_id);
        $permissions = [];
        if (method_exists($user, 'getAllPermissions')) {
            $permissions = $user->getAllPermissions()->pluck('name')->all();
        }

        return [
            'header' => [
                'sub_order_no' => $sub->sub_order_no,
                'status' => $sub->status->value,
                'shop' => $shop['shop_name'] ?? null,
            ],
            'lines' => $sub->lines->map(fn ($l) => [
                'id' => (int) $l->id,
                'product_id' => (int) $l->product_id,
                'qty' => (int) $l->qty,
                'unit_price' => (int) $l->unit_price,
            ])->all(),
            'financials' => [
                'subtotal' => (int) $sub->subtotal,
                'discount' => (int) $sub->discount,
                'total' => (int) $sub->total,
            ],
            'note' => $sub->order?->sections()->where('channel_id', $sub->channel_id)->value('note'),
            'timeline' => $sub->events->map(fn ($e) => [
                'stage' => $e->stage,
                'at' => $e->at?->timezone('Asia/Damascus')->toIso8601String(),
            ])->all(),
            'allowed_actions' => $this->machine->allowed($sub->status, $permissions),
        ];
    }
}
