<?php

declare(strict_types=1);

namespace Modules\Finance\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\ApiController;
use Modules\Finance\Application\Actions\IssueCreditNote;
use Modules\Finance\Application\Actions\RecordOfficePayment;
use Modules\Finance\Application\Actions\SettleRepWallet;
use Modules\Finance\Application\Actions\UpsertRetailerCredit;
use Modules\Finance\Application\Actions\VoidInvoice;
use Modules\Finance\Application\Queries\AgingReport;
use Modules\Finance\Application\Queries\ListInvoices;
use Modules\Finance\Application\Queries\ShowChannelRepWallet;
use Modules\Finance\Application\Queries\ShowInvoice;
use Modules\Finance\Presentation\Http\Requests\IssueCreditNoteRequest;
use Modules\Finance\Presentation\Http\Requests\RecordOfficePaymentRequest;
use Modules\Finance\Presentation\Http\Requests\SettleRepWalletRequest;
use Modules\Finance\Presentation\Http\Requests\UpsertRetailerCreditRequest;
use Modules\Finance\Presentation\Http\Requests\VoidInvoiceRequest;

final class ChannelFinanceController extends ApiController
{
    public function invoices(ListInvoices $query, Request $request): JsonResponse
    {
        return $this->paginated($query($request), fn ($row) => $query->map($row));
    }

    public function showInvoice(ShowInvoice $query, int $id): JsonResponse
    {
        return $this->ok($query($id));
    }

    public function creditNote(IssueCreditNoteRequest $request, IssueCreditNote $action, int $id): JsonResponse
    {
        $result = $action($request->user(), $id, $request->validated());
        if (isset($result['approval_request_id']) && ! isset($result['credit_note_id'])) {
            return $this->ok($result, ['requires_dual_approval' => true]);
        }

        return $this->ok($result);
    }

    public function voidInvoice(VoidInvoiceRequest $request, VoidInvoice $action, int $id): JsonResponse
    {
        $result = $action($request->user(), $id, $request->validated());
        if (isset($result['approval_request_id']) && ! isset($result['status'])) {
            return $this->ok($result, ['requires_dual_approval' => true]);
        }

        return $this->ok($result);
    }

    public function payment(RecordOfficePaymentRequest $request, RecordOfficePayment $action): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated()));
    }

    public function settle(SettleRepWalletRequest $request, SettleRepWallet $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id, $request->validated()));
    }

    public function wallet(ShowChannelRepWallet $query, int $id): JsonResponse
    {
        return $this->ok($query($id));
    }

    public function aging(AgingReport $query, Request $request): JsonResponse
    {
        return $this->ok($query($request));
    }

    public function credit(UpsertRetailerCreditRequest $request, UpsertRetailerCredit $action, int $id): JsonResponse
    {
        return $this->ok($action($request->user(), $id, $request->validated()));
    }
}
