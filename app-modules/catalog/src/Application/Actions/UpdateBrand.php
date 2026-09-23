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
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\InvalidFields;
use Modules\Core\Support\Tenant;

final class UpdateBrand
{
    public function __construct(
        private readonly ReferenceDirectory $refs,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{id: int}
     */
    public function __invoke(object $actor, array $data, int $id): array
    {
        $brand = Brand::query()->whereKey($id)->first();
        if ($brand === null) {
            throw DomainException::of(ErrorCode::NotFound);
        }

        $activityIds = array_map('intval', $data['activity_type_ids'] ?? []);
        if ($activityIds === [] || ! $this->refs->allActivityTypesExist($activityIds)) {
            InvalidFields::throw(['activity_type_ids' => 'catalog.activity_type_not_found']);
        }

        if ($this->nameTaken((string) $data['name_ar'], (int) $brand->id)) {
            InvalidFields::throw(['name_ar' => 'catalog.brand_name_taken']);
        }

        $logoId = $this->mediaId($data['logo'] ?? null);
        if ($logoId === null) {
            InvalidFields::throw(['logo' => 'catalog.brand_logo_required']);
        }
        BrandLogoRules::assert($logoId);

        DB::transaction(function () use ($brand, $data, $activityIds, $actor, $logoId): void {
            $before = ['name_ar' => $brand->name_ar, 'status' => $brand->status->value];

            $brand->update([
                'name_ar' => $data['name_ar'],
                'name_en' => $data['name_en'] ?? null,
                'description' => $data['description'],
                'logo_media_id' => $logoId,
                'banner_media_id' => $this->mediaId($data['banner'] ?? null),
                'order' => (int) ($data['order'] ?? 0),
                'status' => BrandStatus::tryFrom((string) ($data['status'] ?? $brand->status->value)) ?? $brand->status,
            ]);

            BrandActivityType::query()->where('brand_id', $brand->id)->delete();
            foreach ($activityIds as $activityId) {
                BrandActivityType::query()->create([
                    'brand_id' => $brand->id,
                    'activity_type_id' => $activityId,
                ]);
            }

            BrandSlider::query()->where('brand_id', $brand->id)->delete();
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

            $this->audit->record('catalog.brand.update', $actor, 'brand', (int) $brand->id, [
                'before' => $before,
                'after' => ['name_ar' => $brand->name_ar, 'status' => $brand->status->value],
            ], Tenant::currentId());
        });

        return ['id' => (int) $brand->id];
    }

    private function nameTaken(string $nameAr, int $exceptId): bool
    {
        return Brand::query()
            ->where('name_ar', $nameAr)
            ->whereKeyNot($exceptId)
            ->exists();
    }

    private function mediaId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
