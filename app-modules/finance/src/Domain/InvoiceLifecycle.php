<?php

declare(strict_types=1);

namespace Modules\Finance\Domain;

use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\InvalidFields;
use Modules\Finance\Domain\Models\Invoice;

final class InvoiceLifecycle
{
    public function void(Invoice $invoice): void
    {
        if ($invoice->status !== 'open' || (int) $invoice->paid_total !== 0 || (int) $invoice->credited_total !== 0) {
            throw DomainException::of(ErrorCode::IllegalTransition, __('finance.illegal_transition'));
        }

        $invoice->forceFill(['status' => 'void'])->save();
    }

    public function credit(Invoice $invoice, int $amount): void
    {
        if ($invoice->status !== 'open') {
            throw DomainException::of(ErrorCode::IllegalTransition, __('finance.illegal_transition'));
        }

        $remaining = $invoice->remaining();
        if ($amount > $remaining) {
            InvalidFields::throw(['lines' => 'finance.credit_exceeds_remaining']);
        }

        $credited = (int) $invoice->credited_total + $amount;
        $status = $credited >= (int) $invoice->total ? 'credited' : 'open';
        $invoice->forceFill([
            'credited_total' => $credited,
            'status' => $status,
        ])->save();
    }

    public function applyPayment(Invoice $invoice, int $amount): int
    {
        if ($invoice->status !== 'open') {
            return 0;
        }

        $remaining = $invoice->remaining();
        $applied = min($amount, $remaining);
        if ($applied <= 0) {
            return 0;
        }

        $invoice->forceFill(['paid_total' => (int) $invoice->paid_total + $applied])->save();

        return $applied;
    }
}
