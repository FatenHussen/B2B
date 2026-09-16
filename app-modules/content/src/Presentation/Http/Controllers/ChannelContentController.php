<?php

declare(strict_types=1);

namespace Modules\Content\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Content\Domain\Models\Banner;
use Modules\Content\Domain\Models\ChannelIntro;
use Modules\Content\Domain\Models\Slider;
use Modules\Content\Presentation\Http\Requests\StoreBannerRequest;
use Modules\Content\Presentation\Http\Requests\StoreSliderRequest;
use Modules\Content\Presentation\Http\Requests\UpdateIntroRequest;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Http\ApiController;
use Modules\Core\Support\Tenant;

final class ChannelContentController extends ApiController
{
    public function showIntro(): JsonResponse
    {
        $row = ChannelIntro::query()->first();
        if ($row === null) {
            return $this->ok([
                'enabled' => false,
                'text' => null,
                'media_type' => null,
                'media_id' => null,
                'duration' => 0,
                'targeting' => ['activity_type_ids' => [], 'zone_ids' => []],
            ]);
        }

        return $this->ok([
            'enabled' => (bool) $row->enabled,
            'text' => $row->text,
            'media_type' => $row->media_type,
            'media_id' => $row->media_id,
            'duration' => (int) $row->duration,
            'targeting' => $row->targeting ?? ['activity_type_ids' => [], 'zone_ids' => []],
        ]);
    }

    public function updateIntro(UpdateIntroRequest $request): JsonResponse
    {
        $data = $request->validated();
        ChannelIntro::query()->updateOrCreate(
            ['supply_channel_id' => Tenant::currentId()],
            [
                'enabled' => (bool) $data['enabled'],
                'text' => $data['text'] ?? null,
                'media_type' => $data['media_type'] ?? null,
                'media_id' => $data['media_id'] ?? null,
                'duration' => (int) ($data['duration'] ?? 0),
                'targeting' => $data['targeting'] ?? null,
            ],
        );

        return $this->ok(['enabled' => (bool) $data['enabled']]);
    }

    public function banners(Request $request): JsonResponse
    {
        $page = Banner::query()->orderBy('order')->paginate(min((int) $request->input('per_page', 25), 100));

        return $this->paginated($page, fn (Banner $row) => [
            'id' => (int) $row->id,
            'media_type' => $row->media_type,
            'placements' => $row->placements ?? [],
        ]);
    }

    public function storeBanner(StoreBannerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $row = Banner::query()->create([
            'supply_channel_id' => Tenant::currentId(),
            'media_type' => $data['media_type'],
            'media_id' => $data['media_id'],
            'link' => $data['link'] ?? null,
            'placements' => $data['placements'],
            'targeting' => $data['targeting'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'order' => (int) ($data['order'] ?? 0),
            'weight' => (int) ($data['weight'] ?? 1),
            'impressions' => 0,
            'clicks' => 0,
        ]);

        return $this->ok(['id' => (int) $row->id]);
    }

    public function bannerStats(int $id): JsonResponse
    {
        $row = Banner::query()->find($id);
        if ($row === null) {
            throw DomainException::of(ErrorCode::NotFound, __('content.not_found'));
        }

        // Impressions/clicks stay the stored ledger (0 until a serve path writes them).
        // ctr is integer basis points at scale 10^4 — never a fabricated decimal.
        $impressions = (int) $row->impressions;
        $clicks = (int) $row->clicks;
        $ctr = $impressions > 0 ? intdiv($clicks * 10_000, $impressions) : 0;

        return $this->ok([
            'impressions' => $impressions,
            'clicks' => $clicks,
            'ctr' => $ctr,
        ]);
    }

    public function sliders(): JsonResponse
    {
        $rows = Slider::query()->orderBy('id')->get()->map(fn (Slider $row) => [
            'id' => (int) $row->id,
            'name' => $row->name,
            'source' => $row->source,
            'algorithm' => $row->algorithm,
        ]);

        return $this->ok($rows->all());
    }

    public function storeSlider(StoreSliderRequest $request): JsonResponse
    {
        $data = $request->validated();
        $row = Slider::query()->create([
            'supply_channel_id' => Tenant::currentId(),
            'name' => $data['name'],
            'source' => $data['source'],
            'source_ref' => $data['source_ref'] ?? null,
            'algorithm' => $data['algorithm'] ?? null,
            'placements' => $data['placements'] ?? null,
            'items_count' => (int) ($data['items_count'] ?? 12),
            'show_all_button' => (bool) ($data['show_all_button'] ?? false),
            'targeting' => $data['targeting'] ?? null,
        ]);

        return $this->ok(['id' => (int) $row->id]);
    }
}
