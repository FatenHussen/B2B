<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Queries;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Core\Contracts\LoyaltyBalance;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Finance\Domain\Models\Invoice;
use Modules\Finance\Domain\Models\Payment;

final class ShowRetailerAccountSummary
{
    public function __construct(
        private readonly RetailerShoppingContext $shopping,
        private readonly LoyaltyBalance $loyalty,
    ) {}

    /**
     * @return array{
     *     month: string,
     *     orders_total: int,
     *     purchases_total: int,
     *     orders_count: int,
     *     avg_order_value: int,
     *     debt_total: int,
     *     payments_total: int,
     *     points: int
     * }
     */
    public function __invoke(object $user, Request $request): array
    {
        if (! $this->shopping->isRetailer($user)) {
            throw DomainException::of(ErrorCode::NotFound, __('finance.not_found'));
        }

        $retailerId = (int) $this->shopping->for($user)['retailer_id'];
        $month = (string) $request->query('month', now()->timezone('Asia/Damascus')->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->timezone('Asia/Damascus')->format('Y-m');
        }

        $from = Carbon::createFromFormat('Y-m-d', $month.'-01', 'Asia/Damascus')?->startOfMonth()
            ?? now()->startOfMonth();
        $to = $from->copy()->endOfMonth();

        // acrossChannels(), per rule 10: `/app/retailer/*` sets no tenant; retailer_id owns the rows.
        $invoices = Invoice::query()
            ->acrossChannels()
            ->where('retailer_id', $retailerId)
            ->whereBetween('created_at', [$from, $to])
            ->get();

        $ordersTotal = (int) $invoices->sum(fn (Invoice $row) => (int) $row->total);
        $ordersCount = $invoices->count();
        $purchasesTotal = (int) $invoices->sum(fn (Invoice $row) => (int) $row->total - (int) $row->credited_total);

        // Same escape: payments for this retailer across every channel they buy from.
        $paymentsTotal = (int) Payment::query()
            ->acrossChannels()
            ->where('retailer_id', $retailerId)
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        // Same escape: open debt is every open invoice for this retailer, any channel.
        $debtTotal = (int) Invoice::query()
            ->acrossChannels()
            ->where('retailer_id', $retailerId)
            ->where('status', 'open')
            ->get()
            ->sum(fn (Invoice $invoice) => $invoice->remaining());

        $points = $this->loyalty->snapshot((int) $user->getAuthIdentifier(), 'retailer')['points'];

        return [
            'month' => $month,
            'orders_total' => $ordersTotal,
            'purchases_total' => $purchasesTotal,
            'orders_count' => $ordersCount,
            'avg_order_value' => $ordersCount > 0 ? (int) intdiv($ordersTotal, $ordersCount) : 0,
            'debt_total' => $debtTotal,
            'payments_total' => $paymentsTotal,
            'points' => $points,
        ];
    }
}
