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

    /** @return LengthAwarePaginator<int, SubOrder> */
    public function __invoke(object $user, Request $request): LengthAwarePaginator
    {
        $ctx = $this->shopping->for($user);
        $perPage = min((int) $request->input('per_page', 25), 100);
        $status = $request->input('filter.status', 'all');

        // acrossChannels(), per rule 10: `/app/retailer/*` sets no tenant, and a
        // retailer's order list spans every channel they buy from.
        $query = SubOrder::query()->acrossChannels()->where('retailer_id', $ctx['retailer_id']);

        if ($request->filled('filter.channel_id')) {
            $query->where('channel_id', (int) $request->input('filter.channel_id'));
        }

        if ($request->filled('search')) {
            $term = '%'.$request->string('search')->toString().'%';
            $query->where('sub_order_no', 'like', $term);
        }

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
     * Status tab counts — not filtered by the active status chip.
     *
     * @return array<string, int>
     */
    public function statusCounts(object $user, Request $request): array
    {
        $ctx = $this->shopping->for($user);
        // acrossChannels(), per rule 10: same retailer-owned span as the list query —
        // status tab counts must cover every channel the retailer buys from.
        $base = SubOrder::query()->acrossChannels()->where('retailer_id', $ctx['retailer_id']);

        if ($request->filled('filter.channel_id')) {
            $base->where('channel_id', (int) $request->input('filter.channel_id'));
        }
        if ($request->filled('search')) {
            $term = '%'.$request->string('search')->toString().'%';
            $base->where('sub_order_no', 'like', $term);
        }

        $rows = (clone $base)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->map(fn ($c) => (int) $c)
            ->all();

        $all = array_sum($rows);
        $processing = ($rows[SubOrderStatus::Processing->value] ?? 0)
            + ($rows[SubOrderStatus::AwaitingHandover->value] ?? 0);

        return [
            'all' => $all,
            'pending' => $rows[SubOrderStatus::Pending->value] ?? 0,
            'processing' => $processing,
            'on_the_way' => $rows[SubOrderStatus::OnTheWay->value] ?? 0,
            'delivered' => $rows[SubOrderStatus::Delivered->value] ?? 0,
            'cancelled' => ($rows[SubOrderStatus::Cancelled->value] ?? 0)
                + ($rows[SubOrderStatus::Rejected->value] ?? 0),
            'postponed' => $rows[SubOrderStatus::Postponed->value] ?? 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function map(SubOrder $row): array
    {
        $revealed = ! in_array($row->status, [SubOrderStatus::Pending, SubOrderStatus::Rejected], true);
        $invoice = $this->invoices->forSubOrder((int) $row->id);

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
            'can_track' => in_array($row->status, [
                SubOrderStatus::Processing,
                SubOrderStatus::AwaitingHandover,
                SubOrderStatus::OnTheWay,
            ], true),
            'can_view_invoice' => $invoice !== null,
        ];
    }
}
