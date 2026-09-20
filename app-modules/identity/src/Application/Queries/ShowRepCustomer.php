<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Queries;

use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Contracts\RepSellingContext;
use Modules\Identity\Application\Support\RepShopCard;
use Modules\Identity\Domain\Enums\ProfileStatus;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\RepSourcedShop;
use Modules\Identity\Domain\Models\RetailerProfile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ShowRepCustomer
{
    public function __construct(
        private readonly RepSellingContext $selling,
        private readonly ReferenceDirectory $refs,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(AppUser $user, int $id): array
    {
        $ctx = $this->selling->for($user);
        $shopIds = RepSourcedShop::query()
            ->where('rep_id', $ctx['rep_id'])
            ->pluck('retailer_id')
            ->filter()
            ->map(fn ($rid) => (int) $rid)
            ->all();
        $zoneIds = $user->repProfile?->zoneIds() ?? [];

        $profile = RetailerProfile::query()->with('user')->whereKey($id)->first();
        if ($profile === null) {
            throw new NotFoundHttpException;
        }

        $sourced = in_array((int) $profile->id, $shopIds, true);
        $inCoverage = $profile->status === ProfileStatus::Active
            && in_array((int) $profile->zone_id, $zoneIds, true);
        if (! $sourced && ! $inCoverage) {
            throw new NotFoundHttpException;
        }

        $card = RepShopCard::from($profile, $this->refs);
        $card['owner_name'] = $profile->user?->name;
        $card['activity_type_id'] = (int) $profile->activity_type_id;
        $card['categories'] = $profile->categories()->pluck('root_category_id')->map(fn ($cid) => (int) $cid)->all();
        $card['equipments'] = $profile->equipments()->pluck('equipment_id')->map(fn ($eid) => (int) $eid)->all();

        return $card;
    }
}
