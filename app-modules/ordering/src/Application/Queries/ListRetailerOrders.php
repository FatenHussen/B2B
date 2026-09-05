<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Modules\Core\Contracts\ChannelDirectory;
use Modules\Core\Contracts\IssuesInvoice;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Ordering\Domain\Enums\SubOrderStatus;
use Modules\Ordering\Domain\Models\SubOrder;

final class ListRetailerOrders
{
    public function __construct(
        private readonly RetailerShoppingContext $shopping,
        private readonly ChannelDirectory $channels,
        private readonly IssuesInvoice $invoices,
    ) {}

    public function __invoke(object $user, Request $request): LengthAwarePaginator
    {
        $ctx = $this->shopping->for($user);
        $perPage = min((int) $request->input('per_page', 25), 100);
        $status = $request->input('filter.status', 'all');

        $query = SubOrder::query()->where('retailer_id', $ctx['retailer_id']);

        if ($status && $status !== 'all') {
            $query->where(function ($q) use ($status): void {
                if ($status === 'active') {
                    $q->whereNotIn('status', [
                        SubOrderStatus::Delivered->value,
                        SubOrderStatus::Cancelled->value,
                        SubOrderStatus::Rejected->value,
                    ]);
                } elseif ($status === 'processing') {
                    $q->whereIn('status', [
                        SubOrderStatus::Processing->value,
                        SubOrderStatus::AwaitingHandover->value,
                    ]);
                } else {
                    $q->where('status', $status);
                }
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    /**
     * @return array<string, mixed>
     */
    public function map(SubOrder $row): array
    {
        $revealed = ! in_array($row->status, [SubOrderStatus::Pending, SubOrderStatus::Rejected], true);

        return [
            'id' => (int) $row->id,
            'sub_order_no' => $row->sub_order_no,
            'created_at' => $row->created_at?->timezone('Asia/Damascus')->toIso8601String(),
            'supply_channel' => $revealed
                ? ['id' => (int) $row->channel_id, 'name' => $this->channels->name((int) $row->channel_id)]
                : null,
            'status' => $row->status->value,
            'total' => (int) $row->total,
            'can_cancel' => $row->status === SubOrderStatus::Pending,
            'can_reorder' => true,
        ];
    }
}
