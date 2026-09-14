<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use Illuminate\Database\Seeder;
use Modules\Catalog\Domain\Models\Product;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\RetailerProfile;
use Modules\Reference\Domain\Models\ActivityType;
use Modules\Reference\Domain\Models\Currency;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\RootCategory;
use Modules\Reference\Domain\Models\SaleUnit;
use Modules\Reference\Domain\Models\Zone;
use Modules\Tenancy\Domain\Models\Warehouse;
use RuntimeException;

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
     * @return list<int>
     */
    protected function coveredZoneIds(): array
    {
        return array_map(fn (array $pair) => $this->zoneId($pair[0], $pair[1]), self::COVERED_ZONES);
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
}
