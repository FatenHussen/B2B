<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\ApiController;
use Modules\Identity\Application\Actions\RegisterRepCustomer;
use Modules\Identity\Application\Actions\RequestRepZone;
use Modules\Identity\Application\Actions\SetRepDutyStatus;
use Modules\Identity\Application\Queries\ListRepCustomers;
use Modules\Identity\Application\Queries\ListZoneShops;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Presentation\Http\Requests\StoreRepCustomerRequest;
use Modules\Identity\Presentation\Http\Requests\StoreRepZoneRequest;
use Modules\Identity\Presentation\Http\Requests\UpdateRepStatusRequest;

final class RepFieldController extends ApiController
{
    public function customers(Request $request, ListRepCustomers $query): JsonResponse
    {
        /** @var AppUser $user */
        $user = $request->user();

        return $this->paginated($query($user), function ($profile): array {
            return [
                'id' => (int) $profile->id,
                'shop_name' => (string) $profile->shop_name,
                'zone_id' => (int) $profile->zone_id,
                'is_active' => $profile->status->value === 'active',
            ];
        });
    }

    public function storeCustomer(StoreRepCustomerRequest $request, RegisterRepCustomer $action): JsonResponse
    {
        /** @var AppUser $user */
        $user = $request->user();

        return $this->created($action($user, $request->validated()));
    }

    public function requestZone(StoreRepZoneRequest $request, RequestRepZone $action): JsonResponse
    {
        /** @var AppUser $user */
        $user = $request->user();

        return $this->ok($action($user, $request->validated()));
    }

    public function shops(Request $request, ListZoneShops $query, int $id): JsonResponse
    {
        /** @var AppUser $user */
        $user = $request->user();

        return $this->paginated($query($user, $id, $request->query('search')), function ($profile): array {
            return [
                'id' => (int) $profile->id,
                'shop_name' => (string) $profile->shop_name,
                'address' => $profile->address,
                'is_open' => true,
                'is_active' => $profile->status->value === 'active',
                'last_order_at' => null,
            ];
        });
    }

    public function status(UpdateRepStatusRequest $request, SetRepDutyStatus $action): JsonResponse
    {
        /** @var AppUser $user */
        $user = $request->user();

        return $this->ok($action($user, (bool) $request->boolean('on_duty')));
    }
}
