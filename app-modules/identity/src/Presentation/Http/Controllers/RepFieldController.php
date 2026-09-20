<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Http\ApiController;
use Modules\Identity\Application\Actions\RegisterRepCustomer;
use Modules\Identity\Application\Actions\RequestRepZone;
use Modules\Identity\Application\Actions\SetRepDutyStatus;
use Modules\Identity\Application\Queries\ListRepCustomers;
use Modules\Identity\Application\Queries\ListRepZones;
use Modules\Identity\Application\Queries\ListZoneShops;
use Modules\Identity\Application\Queries\ShowRepCustomer;
use Modules\Identity\Application\Queries\ShowRepHome;
use Modules\Identity\Application\Support\RepShopCard;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Presentation\Http\Requests\StoreRepCustomerRequest;
use Modules\Identity\Presentation\Http\Requests\StoreRepZoneRequest;
use Modules\Identity\Presentation\Http\Requests\UpdateRepStatusRequest;

final class RepFieldController extends ApiController
{
    public function __construct(private readonly ReferenceDirectory $refs) {}

    public function customers(Request $request, ListRepCustomers $query): JsonResponse
    {
        /** @var AppUser $user */
        $user = $request->user();

        return $this->paginated($query($user), fn ($profile): array => RepShopCard::from($profile, $this->refs));
    }

    public function showCustomer(Request $request, ShowRepCustomer $query, int $id): JsonResponse
    {
        /** @var AppUser $user */
        $user = $request->user();

        return $this->ok($query($user, $id));
    }

    public function storeCustomer(StoreRepCustomerRequest $request, RegisterRepCustomer $action): JsonResponse
    {
        /** @var AppUser $user */
        $user = $request->user();

        return $this->created($action($user, $request->validated()));
    }

    public function zones(Request $request, ListRepZones $query): JsonResponse
    {
        /** @var AppUser $user */
        $user = $request->user();

        return $this->ok($query($user));
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

        return $this->paginated($query($user, $id, $request->query('search')), fn ($profile): array => RepShopCard::from($profile, $this->refs));
    }

    public function home(Request $request, ShowRepHome $query): JsonResponse
    {
        /** @var AppUser $user */
        $user = $request->user();

        return $this->ok($query($user));
    }

    public function status(UpdateRepStatusRequest $request, SetRepDutyStatus $action): JsonResponse
    {
        /** @var AppUser $user */
        $user = $request->user();

        return $this->ok($action($user, (bool) $request->boolean('on_duty')));
    }
}
