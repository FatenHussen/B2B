<?php

declare(strict_types=1);

namespace Modules\Finance\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\ApiController;
use Modules\Finance\Application\Actions\ExportRetailerStatement;
use Modules\Finance\Application\Actions\RecordRetailerPayment;
use Modules\Finance\Application\Queries\ListRetailerDebts;
use Modules\Finance\Application\Queries\ShowRetailerAccountStatement;
use Modules\Finance\Application\Queries\ShowRetailerAccountSummary;
use Modules\Finance\Presentation\Http\Requests\ExportRetailerStatementRequest;
use Modules\Finance\Presentation\Http\Requests\RecordRetailerPaymentRequest;

final class RetailerFinanceController extends ApiController
{
    public function payment(RecordRetailerPaymentRequest $request, RecordRetailerPayment $action): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated()));
    }

    public function summary(Request $request, ShowRetailerAccountSummary $query): JsonResponse
    {
        return $this->ok($query($request->user(), $request));
    }

    public function statement(Request $request, ShowRetailerAccountStatement $query): JsonResponse
    {
        return $this->ok($query($request->user(), $request));
    }

    public function exportStatement(ExportRetailerStatementRequest $request, ExportRetailerStatement $action): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated()));
    }

    public function debts(Request $request, ListRetailerDebts $query): JsonResponse
    {
        return $this->ok($query($request->user()));
    }
}
