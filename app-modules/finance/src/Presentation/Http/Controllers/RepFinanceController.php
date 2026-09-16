<?php

declare(strict_types=1);

namespace Modules\Finance\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\ApiController;
use Modules\Finance\Application\Actions\CollectRepPayment;
use Modules\Finance\Application\Actions\RecordRepWithdrawal;
use Modules\Finance\Application\Actions\ReserveReceipt;
use Modules\Finance\Application\Queries\ListRepReceivables;
use Modules\Finance\Application\Queries\ListRepWithdrawals;
use Modules\Finance\Application\Queries\ShowRepWallet;
use Modules\Finance\Presentation\Http\Requests\CollectRepPaymentRequest;
use Modules\Finance\Presentation\Http\Requests\RecordRepWithdrawalRequest;

final class RepFinanceController extends ApiController
{
    public function reserve(Request $request, ReserveReceipt $action): JsonResponse
    {
        return $this->ok($action($request->user()));
    }

    public function collect(CollectRepPaymentRequest $request, CollectRepPayment $action): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated()));
    }

    public function wallet(Request $request, ShowRepWallet $query): JsonResponse
    {
        return $this->ok($query($request->user()));
    }

    public function withdraw(RecordRepWithdrawalRequest $request, RecordRepWithdrawal $action): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated()));
    }

    public function withdrawals(Request $request, ListRepWithdrawals $query): JsonResponse
    {
        return $this->ok($query($request->user(), $request));
    }

    public function receivables(Request $request, ListRepReceivables $query): JsonResponse
    {
        return $this->ok($query($request->user()));
    }
}
