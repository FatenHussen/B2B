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
use Modules\Finance\Domain\Models\Invoice;

final class VoidInvoice
{
    public function __construct(
        private readonly RequestsDualApproval $dual,
        private readonly InvoiceLifecycle $lifecycle,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array{reason: string, approval_request_id?: int, approval_reason?: string}  $data
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
            'reason' => (string) $data['reason'],
        ];

        return DB::transaction(function () use ($actor, $invoice, $data, $payload, $invoiceId): array {
            $decision = $this->dual->gate(
                $actor,
                'sc.finance.void_invoice',
                'finance.void_invoice',
                $payload,
                isset($data['approval_request_id']) ? (int) $data['approval_request_id'] : null,
                isset($data['approval_reason']) ? (string) $data['approval_reason'] : null,
            );

            if (! $decision->execute) {
                return ['approval_request_id' => (int) $decision->approvalRequestId];
            }

            $this->lifecycle->void($invoice);

            $this->audit->record('finance.void_invoice', $actor, 'invoice', $invoiceId, [
                'reason' => (string) $data['reason'],
            ], (int) Tenant::currentId());

            return ['status' => 'void'];
        });
    }
}
