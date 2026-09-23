<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Queries;

use Modules\Catalog\Domain\Models\Brand;
use Modules\Catalog\Domain\Models\BrandActivityType;
use Modules\Catalog\Domain\Models\BrandSlider;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\MediaUrl;

final class ShowChannelBrand
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(int $id): array
    {
        $brand = Brand::query()->whereKey($id)->first();

        if ($brand === null) {
            throw DomainException::of(ErrorCode::NotFound);
        }

        $logoId = $brand->logo_media_id !== null ? (int) $brand->logo_media_id : null;
        $bannerId = $brand->banner_media_id !== null ? (int) $brand->banner_media_id : null;

        return [
            'id' => (int) $brand->id,
            'name_ar' => $brand->name_ar,
            'name_en' => $brand->name_en,
            'logo' => $logoId !== null ? (string) $logoId : null,
            'logo_url' => MediaUrl::of($logoId),
            'banner' => $bannerId !== null ? (string) $bannerId : null,
            'banner_url' => MediaUrl::of($bannerId),
            'description' => $brand->description,
            'activity_type_ids' => BrandActivityType::query()
                ->where('brand_id', $brand->id)
                ->pluck('activity_type_id')
                ->map(fn ($typeId) => (int) $typeId)
                ->values()
                ->all(),
            'order' => (int) $brand->order,
            'status' => $brand->status->value,
            'sliders' => BrandSlider::query()
                ->where('brand_id', $brand->id)
                ->orderBy('order')
                ->get()
                ->map(fn (BrandSlider $slider) => [
                    'id' => (int) $slider->id,
                    'name' => $slider->name,
                    'source' => $slider->source,
                    'source_id' => $slider->source_id !== null ? (int) $slider->source_id : null,
                    'count' => (int) $slider->count,
                    'order' => (int) $slider->order,
                ])
                ->all(),
        ];
    }
}
