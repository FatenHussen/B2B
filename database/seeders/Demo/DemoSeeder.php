<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Domain\Models\ChannelMediaLibrary;
use Modules\Catalog\Domain\Models\Product;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\RetailerProfile;
use Modules\Reference\Domain\Models\ActivityType;
use Modules\Reference\Domain\Models\ChannelZone;
use Modules\Reference\Domain\Models\Currency;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\RootCategory;
use Modules\Reference\Domain\Models\SaleUnit;
use Modules\Reference\Domain\Models\Zone;
use Modules\Tenancy\Domain\Models\Warehouse;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Shared lookups for the demo seeders.
 *
 * Every demo seeder runs inside `Tenant::as()` set by `DemoDataSeeder`, so a query on a
 * channel-scoped model already filters to the demo channel and a create fills its channel
 * column. The helpers here resolve rows by the natural key each seeder wrote them under —
 * SKU, phone, name — so a later seeder never has to know the id an earlier one was given,
 * and re-running the whole set finds the same rows instead of adding a second copy.
 */
abstract class DemoSeeder extends Seeder
{
    public const WAREHOUSE_MAIN = 'المستودع الرئيسي - دمشق';

    public const WAREHOUSE_ALEPPO = 'مستودع حلب';

    /**
     * Zones the demo channel covers, as `[governorate code, zone name]`.
     *
     * Names must match `ReferenceSeeder` exactly — that seeder is what creates them.
     *
     * @var list<array{0: string, 1: string}>
     */
    public const COVERED_ZONES = [
        ['DI', 'المزة'],
        ['DI', 'المالكي'],
        ['DI', 'كفرسوسة'],
        ['DI', 'الميدان'],
        ['DI', 'باب توما'],
        ['DI', 'القصاع'],
        ['DI', 'برزة'],
        ['RD', 'جرمانا'],
        ['RD', 'صحنايا'],
        ['RD', 'قدسيا'],
        ['HL', 'الحمدانية'],
        ['HL', 'حلب الجديدة'],
    ];

    /** @var list<string> */
    public const RETAILER_PHONES = [
        '+963931000001',
        '+963931000002',
        '+963931000003',
        '+963931000004',
        '+963931000005',
        '+963931000006',
        '+963931000007',
        '+963931000008',
    ];

    /** @var list<string> */
    public const REP_PHONES = [
        '+963932000001',
        '+963932000002',
        '+963932000003',
    ];

    /**
     * Channels that have a catalog. Offers and rep field data are per channel,
     * and a channel with no products has nothing for an offer to point at.
     *
     * @return list<int>
     */
    protected function catalogChannelIds(): array
    {
        return Tenant::withoutScope(fn () => DB::table('products')
            ->distinct()
            ->orderBy('supply_channel_id')
            ->pluck('supply_channel_id')
            ->map(fn ($id) => (int) $id)
            ->all());
    }

    protected function channelId(): int
    {
        $id = Tenant::currentId();

        if ($id === null) {
            throw new RuntimeException(static::class.' must run inside DemoDataSeeder, which sets the tenant.');
        }

        return $id;
    }

    protected function zoneId(string $governorateCode, string $zoneName): int
    {
        $governorateId = Governorate::query()->where('code', $governorateCode)->value('id');

        if ($governorateId === null) {
            throw new RuntimeException("Governorate {$governorateCode} is missing — run ReferenceSeeder first.");
        }

        $id = Zone::query()
            ->where('governorate_id', $governorateId)
            ->where('name', $zoneName)
            ->value('id');

        if ($id === null) {
            throw new RuntimeException("Zone {$zoneName} ({$governorateCode}) is missing — run ReferenceSeeder first.");
        }

        return (int) $id;
    }

    /**
     * The zones the current tenant covers, from its `channel_zone` rows — so the catalog
     * and people seeders work for any channel whose coverage has been written, not only
     * the demo channel's `COVERED_ZONES`. `DemoChannelSeeder` (demo) and
     * `DemoPlatformSeeder` (the others) write coverage before anything reads it.
     *
     * @return list<int>
     */
    protected function coveredZoneIds(): array
    {
        $ids = ChannelZone::query()->orderBy('zone_id')->pluck('zone_id')->map(fn ($id) => (int) $id)->all();

        if ($ids === []) {
            throw new RuntimeException('Channel '.$this->channelId().' has no coverage — seed its channel_zone rows first.');
        }

        return $ids;
    }

    protected function warehouseId(string $name): int
    {
        return (int) Warehouse::query()->where('name', $name)->firstOrFail()->id;
    }

    protected function productId(string $sku): int
    {
        return (int) Product::query()->where('sku', $sku)->firstOrFail()->id;
    }

    protected function retailerProfileId(string $phone): int
    {
        $userId = AppUser::query()->where('phone', $phone)->firstOrFail()->id;

        return (int) RetailerProfile::query()->where('app_user_id', $userId)->firstOrFail()->id;
    }

    protected function repUserId(string $phone): int
    {
        return (int) AppUser::query()->where('phone', $phone)->firstOrFail()->id;
    }

    protected function activityTypeId(string $name): int
    {
        return (int) ActivityType::query()->where('name', $name)->firstOrFail()->id;
    }

    /**
     * @return list<int>
     */
    protected function activityTypeIds(): array
    {
        return ActivityType::query()->orderBy('order')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    protected function rootCategoryId(string $name): int
    {
        return (int) RootCategory::query()->where('name', $name)->firstOrFail()->id;
    }

    protected function saleUnitId(string $name): int
    {
        return (int) SaleUnit::query()->where('name', $name)->firstOrFail()->id;
    }

    protected function baseCurrencyId(): int
    {
        return (int) Currency::query()->where('is_base', true)->firstOrFail()->id;
    }

    /**
     * A channel-scoped Spatie image the catalog and offers can point at.
     *
     * Keyed by `demo-{key}` on the channel library so a second seed finds the same
     * row instead of writing another file. Colours come from the key so product and
     * offer cards do not all look identical in the rep/retailer apps.
     */
    protected function demoImageMediaId(string $key, int $width = 640, int $height = 640): int
    {
        $library = ChannelMediaLibrary::query()->firstOrCreate(
            ['supply_channel_id' => $this->channelId()],
            [],
        );

        $name = 'demo-'.$key;
        $existingId = Media::query()
            ->where('model_type', $library->getMorphClass())
            ->where('model_id', $library->id)
            ->where('collection_name', 'image')
            ->where('name', $name)
            ->value('id');

        if ($existingId !== null) {
            return (int) $existingId;
        }

        $fileStem = 'demo-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $key);
        $path = $this->writeDemoJpeg($fileStem, $width, $height, $key);

        try {
            $media = $library
                ->addMedia($path)
                ->usingName($name)
                ->usingFileName($fileStem.'.jpg')
                ->withCustomProperties([
                    'width' => $width,
                    'height' => $height,
                    'demo_key' => $key,
                ])
                ->toMediaCollection('image');
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        return (int) $media->id;
    }

    private function writeDemoJpeg(string $fileStem, int $width, int $height, string $key): string
    {
        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            throw new RuntimeException('GD could not allocate a demo image.');
        }

        $hash = crc32($key);
        $r = 40 + ($hash & 0x7F);
        $g = 40 + (($hash >> 8) & 0x7F);
        $b = 40 + (($hash >> 16) & 0x7F);
        $bg = imagecolorallocate($image, $r, $g, $b);
        $fg = imagecolorallocate($image, 255, 255, 255);
        $band = imagecolorallocate($image, max(0, $r - 30), max(0, $g - 30), max(0, $b - 30));
        if ($bg === false || $fg === false || $band === false) {
            imagedestroy($image);
            throw new RuntimeException('GD could not allocate demo colours.');
        }

        imagefilledrectangle($image, 0, 0, $width, $height, $bg);
        imagefilledrectangle($image, 0, (int) ($height * 0.72), $width, $height, $band);

        $label = strtoupper(substr(preg_replace('/[^A-Za-z0-9_-]+/', '-', $key) ?? $key, 0, 18));
        imagestring($image, 5, 24, (int) ($height * 0.78), $label, $fg);
        imagestring($image, 3, 24, 28, 'B2B DEMO', $fg);

        $dir = storage_path('app/demo-seed');
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            imagedestroy($image);
            throw new RuntimeException("Could not create {$dir}");
        }

        $path = $dir.DIRECTORY_SEPARATOR.$fileStem.'.jpg';
        if (! imagejpeg($image, $path, 85)) {
            imagedestroy($image);
            throw new RuntimeException("Could not write {$path}");
        }
        imagedestroy($image);

        return $path;
    }
}
