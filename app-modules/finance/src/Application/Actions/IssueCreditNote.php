<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\RequestsDualApproval;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Finance\Domain\InvoiceLifecycle;
use Modules\Finance\Domain\Models\CreditNote;
use Modules\Finance\Domain\Models\CreditNoteLine;
use Modules\Finance\Domain\Models\Invoice;
use Modules\Finance\Domain\Models\InvoiceLine;

final class IssueCreditNote
{
    public function __construct(
        private readonly RequestsDualApproval $dual,
        private readonly InvoiceLifecycle $lifecycle,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array{lines: list<array{line_id: int, qty: int, amount: int}>, reason: string, approval_request_id?: int, approval_reason?: string}  $data
     * @return array<string, int|string>
     */
    public function __invoke(object $actor, int $invoiceId, array $data): array
    {
        $invoice = Invoice::query()->find($invoiceId);
        if ($invoice === null) {
            throw DomainException::of(ErrorCode::NotFound, __('finance.not_found'));
        }

        $payload = [
            'invoice_id' => $invoiceId,
            'lines' => $data['lines'],
            'reason' => (string) $data['reason'],
        ];

        return DB::transaction(function () use ($actor, $invoice, $data, $payload, $invoiceId): array {
            $decision = $this->dual->gate(
                $actor,
                'sc.finance.credit_note',
                'finance.credit_note',
                $payload,
                isset($data['approval_request_id']) ? (int) $data['approval_request_id'] : null,
                isset($data['approval_reason']) ? (string) $data['approval_reason'] : null,
            );

            if (! $decision->execute) {
                return ['approval_request_id' => (int) $decision->approvalRequestId];
            }

            $total = 0;
            foreach ($data['lines'] as $line) {
                $total += (int) $line['amount'];
                $lineId = (int) $line['line_id'];
                $owned = InvoiceLine::query()->where('invoice_id', $invoiceId)->whereKey($lineId)->exists();
                if (! $owned) {
                    throw DomainException::of(ErrorCode::NotFound, __('finance.not_found'));
                }
            }

            $this->lifecycle->credit($invoice, $total);

            $note = CreditNote::query()->create([
                'supply_channel_id' => (int) Tenant::currentId(),
                'invoice_id' => $invoiceId,
                'no' => 'CN-tmp',
                'total' => $total,
                'reason' => (string) $data['reason'],
            ]);
            $note->forceFill(['no' => 'CN-'.$note->id])->save();

            foreach ($data['lines'] as $line) {
                CreditNoteLine::query()->create([
                    'credit_note_id' => $note->id,
                    'invoice_line_id' => (int) $line['line_id'],
                    'qty' => (int) $line['qty'],
                    'amount' => (int) $line['amount'],
                ]);
            }

            $this->audit->record('finance.credit_note', $actor, 'credit_note', (int) $note->id, [
                'invoice_id' => $invoiceId,
                'total' => $total,
            ], (int) Tenant::currentId());

            return ['credit_note_id' => (int) $note->id, 'no' => (string) $note->no];
        });
    }
}
