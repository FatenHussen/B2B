<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Queries;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Finance\Domain\Models\CreditNote;
use Modules\Finance\Domain\Models\Invoice;
use Modules\Finance\Domain\Models\Payment;

final class ShowRetailerAccountStatement
{
    public function __construct(private readonly RetailerShoppingContext $shopping) {}

    /**
     * @return array{opening_balance: int, rows: list<array{date: string, type: string, ref_no: string, debit: int, credit: int, balance: int}>, closing_balance: int}
     */
    public function __invoke(object $user, Request $request): array
    {
        if (! $this->shopping->isRetailer($user)) {
            throw DomainException::of(ErrorCode::NotFound, __('finance.not_found'));
        }

        $retailerId = (int) $this->shopping->for($user)['retailer_id'];
        $from = Carbon::parse((string) $request->query('date_from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse((string) $request->query('date_to', now()->toDateString()))->endOfDay();
        $channelFilter = $request->query('channel_id');
        $channelId = is_numeric($channelFilter) ? (int) $channelFilter : null;

        $movements = $this->movements($retailerId, $channelId);
        $opening = 0;
        $rows = [];
        $balance = 0;

        foreach ($movements as $row) {
            $at = Carbon::parse($row['at']);
            if ($at->lt($from)) {
                $opening += $row['debit'] - $row['credit'];
                $balance = $opening;

                continue;
            }
            if ($at->gt($to)) {
                continue;
            }
            if ($rows === []) {
                $balance = $opening;
            }
            $balance += $row['debit'] - $row['credit'];
            $rows[] = [
                'date' => $at->timezone('Asia/Damascus')->toDateString(),
                'type' => $row['type'],
                'ref_no' => $row['ref_no'],
                'debit' => $row['debit'],
                'credit' => $row['credit'],
                'balance' => $balance,
            ];
        }

        if ($rows === []) {
            $balance = $opening;
        }

        return [
            'opening_balance' => $opening,
            'rows' => $rows,
            'closing_balance' => $balance,
        ];
    }

    /**
     * @return list<array{at: string, type: string, ref_no: string, debit: int, credit: int}>
     */
    private function movements(int $retailerId, ?int $channelId): array
    {
        $out = [];

        // acrossChannels(), per rule 10: statement spans channels; retailer_id is the owner filter.
        $invoices = Invoice::query()
            ->acrossChannels()
            ->where('retailer_id', $retailerId)
            ->when($channelId !== null, fn ($q) => $q->where('supply_channel_id', $channelId))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($invoices as $invoice) {
            $out[] = [
                'at' => $invoice->created_at?->toIso8601String() ?? now()->toIso8601String(),
                'type' => 'invoice',
                'ref_no' => (string) $invoice->no,
                'debit' => (int) $invoice->total,
                'credit' => 0,
            ];
        }

        // Same escape: payments for this retailer (optional channel filter).
        $payments = Payment::query()
            ->acrossChannels()
            ->where('retailer_id', $retailerId)
            ->when($channelId !== null, fn ($q) => $q->where('supply_channel_id', $channelId))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($payments as $payment) {
            $out[] = [
                'at' => ($payment->paid_at ?? $payment->created_at)?->toIso8601String() ?? now()->toIso8601String(),
                'type' => 'payment',
                'ref_no' => (string) $payment->receipt_no,
                'debit' => 0,
                'credit' => (int) $payment->amount,
            ];
        }

        $invoiceIds = $invoices->map(fn (Invoice $i) => (int) $i->id)->all();
        if ($invoiceIds !== []) {
            // Same escape: credit notes via invoices already scoped to this retailer.
            $notes = CreditNote::query()
                ->acrossChannels()
                ->whereIn('invoice_id', $invoiceIds)
                ->when($channelId !== null, fn ($q) => $q->where('supply_channel_id', $channelId))
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            foreach ($notes as $note) {
                $out[] = [
                    'at' => $note->created_at?->toIso8601String() ?? now()->toIso8601String(),
                    'type' => 'credit_note',
                    'ref_no' => (string) $note->no,
                    'debit' => 0,
                    'credit' => (int) $note->total,
                ];
            }
        }

        usort($out, function (array $a, array $b): int {
            $cmp = strcmp($a['at'], $b['at']);

            return $cmp !== 0 ? $cmp : strcmp($a['ref_no'], $b['ref_no']);
        });

        return $out;
    }
}
