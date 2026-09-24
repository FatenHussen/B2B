<?php

declare(strict_types=1);

namespace Modules\Content\Application\Actions;

use Modules\Content\Domain\Models\Banner;
use Modules\Core\Contracts\RepSellingContext;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Domain\Exceptions\DomainException;

final class RecordBannerClick
{
    public function __construct(
        private readonly RetailerShoppingContext $shopping,
        private readonly RepSellingContext $selling,
    ) {}

    /**
     * @return array{clicks: int}
     */
    public function __invoke(object $user, int $bannerId): array
    {
        $channelIds = $this->channelIds($user);
        if ($channelIds === []) {
            throw new DomainException(__('content.not_found'), 'not_found', 404);
        }

        // Lifted, per rule 10: `/app/content/banners/{id}/click` sets no tenant;
        // whereIn channel ids from the caller's covering channels is the owner filter.
        $row = Banner::withoutGlobalScope('channel')
            ->whereKey($bannerId)
            ->whereIn('supply_channel_id', $channelIds)
            ->first();

        if ($row === null) {
            throw new DomainException(__('content.not_found'), 'not_found', 404);
        }

        $row->increment('clicks');

        return ['clicks' => (int) $row->fresh()?->clicks];
    }

    /**
     * @return list<int>
     */
    private function channelIds(object $user): array
    {
        if ($this->shopping->isRetailer($user)) {
            return $this->shopping->for($user)['channel_ids'];
        }
        if ($this->selling->isRep($user)) {
            return $this->selling->for($user)['channel_ids'];
        }

        return [];
    }
}
