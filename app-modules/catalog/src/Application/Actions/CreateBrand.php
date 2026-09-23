<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Application\Support\BrandLogoRules;
use Modules\Catalog\Domain\Enums\BrandStatus;
use Modules\Catalog\Domain\Models\Brand;
use Modules\Catalog\Domain\Models\BrandActivityType;
use Modules\Catalog\Domain\Models\BrandSlider;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Support\InvalidFields;
use Modules\Core\Support\Tenant;

final class CreateBrand
{
    public function __construct(
        private readonly ReferenceDirectory $refs,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{id: int}
     */
    public function __invoke(object $actor, array $data): array
    {
        $activityIds = array_map('intval', $data['activity_type_ids'] ?? []);
        if ($activityIds === [] || ! $this->refs->allActivityTypesExist($activityIds)) {
            InvalidFields::throw(['activity_type_ids' => 'catalog.activity_type_not_found']);
        }

        if (Brand::query()->where('name_ar', $data['name_ar'])->exists()) {
            InvalidFields::throw(['name_ar' => 'catalog.brand_name_taken']);
        }

        $logoId = $this->mediaId($data['logo'] ?? null);
        if ($logoId === null) {
            InvalidFields::throw(['logo' => 'catalog.brand_logo_required']);
        }
        BrandLogoRules::assert($logoId);

        $brand = DB::transaction(function () use ($data, $activityIds, $actor, $logoId): Brand {
            $brand = Brand::query()->create([
                'name_ar' => $data['name_ar'],
                'name_en' => $data['name_en'] ?? null,
                'description' => $data['description'],
                'logo_media_id' => $logoId,
                'banner_media_id' => $this->mediaId($data['banner'] ?? null),
                'order' => (int) ($data['order'] ?? 0),
                'status' => BrandStatus::tryFrom((string) ($data['status'] ?? 'active')) ?? BrandStatus::Active,
            ]);

            foreach ($activityIds as $id) {
                BrandActivityType::query()->create([
                    'brand_id' => $brand->id,
                    'activity_type_id' => $id,
                ]);
            }

            foreach ($data['sliders'] ?? [] as $slider) {
                BrandSlider::query()->create([
                    'brand_id' => $brand->id,
                    'name' => $slider['name'],
                    'source' => $slider['source'],
                    'source_id' => $slider['source_id'] ?? null,
                    'count' => (int) ($slider['count'] ?? 0),
                    'order' => (int) ($slider['order'] ?? 0),
                ]);
            }

            $this->audit->record('catalog.brand.create', $actor, 'brand', (int) $brand->id, [
                'after' => ['name_ar' => $brand->name_ar],
            ], Tenant::currentId());

            return $brand;
        });

        return ['id' => (int) $brand->id];
    }

    private function mediaId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
