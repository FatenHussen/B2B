<?php

declare(strict_types=1);

namespace Modules\Content\Application\Queries;

use Illuminate\Support\Carbon;
use Modules\Content\Domain\Models\Banner;
use Modules\Content\Domain\Models\HomeBlock;
use Modules\Content\Domain\Models\Slider;
use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\OfferFeed;
use Modules\Core\Contracts\RepSellingContext;
use Modules\Core\Contracts\RetailerShoppingContext;

final class ListHomeBlocks
{
    public function __construct(
        private readonly RetailerShoppingContext $shopping,
        private readonly RepSellingContext $selling,
        private readonly CatalogProductLookup $products,
        private readonly OfferFeed $offers,
    ) {}

    /**
     * @return array{banners: list<array<string, mixed>>, sliders: list<array<string, mixed>>}
     */
    public function __invoke(object $user): array
    {
        [$channelIds, $zoneId, $activityTypeId] = $this->audience($user);
        if ($channelIds === []) {
            return ['banners' => [], 'sliders' => []];
        }

        $shopping = [
            'zone_id' => $zoneId,
            'activity_type_id' => $activityTypeId,
            'channel_ids' => $channelIds,
        ];

        $now = now();
        $banners = [];
        $sliders = [];

        $blocks = HomeBlock::query()
            ->whereIn('supply_channel_id', $channelIds)
            ->where(function ($q) use ($now): void {
                $q->whereNull('active_from')->orWhere('active_from', '<=', $now);
            })
            ->where(function ($q) use ($now): void {
                $q->whereNull('active_to')->orWhere('active_to', '>=', $now);
            })
            ->orderBy('order')
            ->get();

        foreach ($blocks as $block) {
            if (! $this->matches($block->targeting, $activityTypeId, $zoneId)) {
                continue;
            }
            $payload = is_array($block->payload) ? $block->payload : [];
            if ($block->type === 'banner') {
                $banners[] = [
                    'id' => (int) $block->id,
                    'image' => $payload['image'] ?? $payload['media_id'] ?? null,
                    'link' => $payload['link'] ?? null,
                ];
            } else {
                $sliders[] = [
                    'key' => $block->type,
                    'title' => $block->title ?: $block->type,
                    'items' => $payload['items'] ?? [],
                    'show_all' => (bool) ($payload['show_all'] ?? true),
                ];
            }
        }

        // Lifted, per rule 10: `/app/content/home-blocks` sets no tenant; whereIn channel ids is the caller.
        $channelBanners = Banner::withoutGlobalScope('channel')
            ->whereIn('supply_channel_id', $channelIds)
            ->orderBy('order')
            ->get();
        foreach ($channelBanners as $row) {
            if (! $this->inWindow($row->starts_at, $row->ends_at, $now)) {
                continue;
            }
            if (! $this->matches($row->targeting, $activityTypeId, $zoneId)) {
                continue;
            }
            $placements = $row->placements ?? [];
            if ($placements !== [] && ! $this->isHomePlacement($placements)) {
                continue;
            }
            $banners[] = [
                'id' => (int) $row->id,
                'image' => $row->media_id,
                'link' => $row->link,
            ];
            $row->increment('impressions');
        }

        // Same lift as banners — Slider is strict and this route has no tenant.
        $channelSliders = Slider::withoutGlobalScope('channel')
            ->whereIn('supply_channel_id', $channelIds)
            ->orderBy('id')
            ->get();
        foreach ($channelSliders as $row) {
            if (! $this->matches($row->targeting, $activityTypeId, $zoneId)) {
                continue;
            }
            $placements = $row->placements ?? [];
            if ($placements !== [] && ! $this->isHomePlacement($placements)) {
                continue;
            }
            $sliders[] = [
                'key' => $row->algorithm ?: $row->source,
                'title' => $row->name,
                'items' => $this->sliderItems($row, $shopping),
                'show_all' => (bool) $row->show_all_button,
            ];
        }

        return ['banners' => $banners, 'sliders' => $sliders];
    }

    /**
     * @param  array{zone_id: int, activity_type_id: int, channel_ids: list<int>}  $shopping
     * @return list<array<string, mixed>>
     */
    private function sliderItems(Slider $row, array $shopping): array
    {
        $limit = max(1, (int) $row->items_count);
        $channelId = (int) $row->supply_channel_id;

        if ($row->source === 'offers') {
            return $this->offers->sliderFor(
                $shopping['zone_id'],
                $shopping['activity_type_id'],
                [$channelId],
            );
        }

        return $this->products->sliderCards(
            $channelId,
            (string) $row->source,
            $row->source_ref,
            $row->algorithm,
            $limit,
            $shopping,
        );
    }

    /**
     * @return array{0: list<int>, 1: int, 2: int}
     */
    private function audience(object $user): array
    {
        if ($this->shopping->isRetailer($user)) {
            $ctx = $this->shopping->for($user);

            return [$ctx['channel_ids'], $ctx['zone_id'], $ctx['activity_type_id']];
        }

        if ($this->selling->isRep($user)) {
            $ctx = $this->selling->for($user);

            return [$ctx['channel_ids'], (int) ($ctx['default_zone_id'] ?? 0), (int) ($ctx['activity_type_id'] ?? 0)];
        }

        return [[], 0, 0];
    }

    /**
     * @param  array<string, mixed>|null  $targeting
     */
    private function matches(?array $targeting, int $activityTypeId, int $zoneId): bool
    {
        if ($targeting === null || $targeting === []) {
            return true;
        }
        $activities = $targeting['activity_type_ids'] ?? [];
        $zones = $targeting['zone_ids'] ?? [];
        if (is_array($activities) && $activities !== [] && ! in_array($activityTypeId, $activities, true)) {
            return false;
        }
        if (is_array($zones) && $zones !== [] && ! in_array($zoneId, $zones, true)) {
            return false;
        }

        return true;
    }

    /**
     * @param  list<string>  $placements
     */
    private function isHomePlacement(array $placements): bool
    {
        foreach ($placements as $placement) {
            if (str_contains((string) $placement, 'home')) {
                return true;
            }
        }

        return false;
    }

    private function inWindow(mixed $from, mixed $to, Carbon $now): bool
    {
        if ($from instanceof Carbon && $from->gt($now)) {
            return false;
        }
        if ($to instanceof Carbon && $to->lt($now)) {
            return false;
        }

        return true;
    }
}
