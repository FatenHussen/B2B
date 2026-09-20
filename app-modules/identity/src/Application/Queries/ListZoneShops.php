<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Core\Contracts\RepDutyLookup;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Identity\Domain\Enums\ProfileStatus;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\RetailerProfile;

final class ListZoneShops
{
    public function __construct(private readonly RepDutyLookup $duty) {}

    public function __invoke(AppUser $user, int $zoneId, ?string $search = null): LengthAwarePaginator
    {
        $zones = $this->duty->zoneIds((int) $user->id);
        if (! in_array($zoneId, $zones, true)) {
            throw new DomainException(__('identity.zone_not_covered'), 'insufficient_permission', 403);
        }

        $perPage = min((int) request('per_page', 25), 100);
        $query = RetailerProfile::query()
            ->with('user')
            ->where('zone_id', $zoneId)
            ->where('status', ProfileStatus::Active);

        if ($search !== null && $search !== '') {
            $query->where('shop_name', 'like', '%'.$search.'%');
        }

        return $query->orderBy('shop_name')->paginate($perPage);
    }
}
