<?php

declare(strict_types=1);

namespace Modules\Delivery\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\ApiController;
use Modules\Delivery\Application\DeliveryWorkspace;
use Modules\Delivery\Presentation\Http\Requests\CompleteDeliveryRequest;
use Modules\Delivery\Presentation\Http\Requests\FailDeliveryRequest;
use Modules\Delivery\Presentation\Http\Requests\PatchDeliveryLineRequest;
use Modules\Delivery\Presentation\Http\Requests\PingRequest;
use Modules\Delivery\Presentation\Http\Requests\PostponeDeliveryRequest;
use Modules\Delivery\Presentation\Http\Requests\RateRepRequest;

final class DeliveryController extends ApiController
{
    public function index(Request $request, DeliveryWorkspace $ops): JsonResponse
    {
        $zone = $request->input('filter.zone_id');

        return $this->ok($ops->listForRep($request->user(), $zone !== null ? (int) $zone : null));
    }

    public function show(Request $request, DeliveryWorkspace $ops, int $id): JsonResponse
    {
        return $this->ok($ops->detail($id, $request->user()));
    }

    public function patchLine(PatchDeliveryLineRequest $request, DeliveryWorkspace $ops, int $id, int $lineId): JsonResponse
    {
        return $this->ok($ops->patchLine($id, $lineId, $request->validated(), $request->user()));
    }

    public function complete(CompleteDeliveryRequest $request, DeliveryWorkspace $ops, int $id): JsonResponse
    {
        return $this->ok($ops->complete($id, $request->validated(), $request->user()));
    }

    public function postpone(PostponeDeliveryRequest $request, DeliveryWorkspace $ops, int $id): JsonResponse
    {
        return $this->ok($ops->postpone($id, $request->validated(), $request->user()));
    }

    public function fail(FailDeliveryRequest $request, DeliveryWorkspace $ops, int $id): JsonResponse
    {
        return $this->ok($ops->fail($id, $request->validated(), $request->user()));
    }

    public function ping(PingRequest $request, DeliveryWorkspace $ops): JsonResponse
    {
        return $this->ok($ops->ping($request->user(), $request->validated()['pings']));
    }

    public function receipt(Request $request, DeliveryWorkspace $ops, int $subOrderId): JsonResponse
    {
        return $this->ok($ops->detail($subOrderId, $request->user()));
    }

    public function patchReceiptLine(PatchDeliveryLineRequest $request, DeliveryWorkspace $ops, int $id, int $lineId): JsonResponse
    {
        return $this->ok($ops->patchLine($id, $lineId, $request->validated(), $request->user()));
    }

    public function confirmReceipt(CompleteDeliveryRequest $request, DeliveryWorkspace $ops, int $id): JsonResponse
    {
        $payload = $request->validated();
        if (isset($payload['lines'])) {
            foreach ($payload['lines'] as $i => $line) {
                if (isset($line['qty_received'])) {
                    $payload['lines'][$i]['qty_delivered'] = $line['qty_received'];
                }
            }
        }

        return $this->ok($ops->complete($id, $payload, $request->user()));
    }

    public function rate(RateRepRequest $request, DeliveryWorkspace $ops, int $id): JsonResponse
    {
        return $this->ok($ops->rate($request->user(), $id, $request->validated()));
    }
}
