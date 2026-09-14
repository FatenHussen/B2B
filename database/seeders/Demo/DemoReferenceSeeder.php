<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use Modules\Core\Domain\ValueObjects\Money;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\ActivityType;
use Modules\Reference\Domain\Models\Currency;
use Modules\Reference\Domain\Models\Equipment;
use Modules\Reference\Domain\Models\FxRate;
use Modules\Reference\Domain\Models\RootCategory;
use Modules\Reference\Domain\Models\SaleUnit;

/**
 * Platform-wide lookup rows the demo channel's data hangs off.
 *
 * Governorates and zones come from `ReferenceSeeder`; this adds the remaining reference
 * tables it leaves empty. None of these are channel-owned, so this runs the same whichever
 * tenant is set. Rows match on name, so a reseed updates order and leaves status alone.
 */
final class DemoReferenceSeeder extends DemoSeeder
{
    /** @var list<array{0: string, 1: string}> */
    private const ACTIVITY_TYPES = [
        ['سوبر ماركت', 'store'],
        ['بقالة', 'shop'],
        ['مطعم', 'restaurant'],
        ['كافيه', 'coffee'],
        ['صيدلية', 'pharmacy'],
        ['محل حلويات', 'cake'],
    ];

    /** @var list<array{0: string, 1: string}> */
    private const ROOT_CATEGORIES = [
        ['مواد غذائية', 'grocery'],
        ['مشروبات', 'drink'],
        ['ألبان وأجبان', 'dairy'],
        ['منظفات', 'cleaning'],
        ['عناية شخصية', 'care'],
        ['حلويات ومقرمشات', 'snack'],
    ];

    /** @var list<array{0: string, 1: string, 2: int}> */
    private const SALE_UNITS = [
        ['قطعة', 'pc', 1],
        ['عبوة', 'pack', 6],
        ['كرتونة', 'ctn', 12],
        ['كيلوغرام', 'kg', 1],
        ['شوال', 'bag', 1],
    ];

    /** @var list<string> */
    private const EQUIPMENTS = [
        'براد عرض',
        'فريزر',
        'رفوف عرض',
        'ميزان إلكتروني',
        'صندوق دفع',
    ];

    /**
     * USD → SYP at the fixed 10^6 scale of rule 7: 13,000 SYP per dollar.
     */
    private const USD_TO_SYP = 13_000 * Money::FX_UNIT;

    public function run(): void
    {
        foreach (self::ACTIVITY_TYPES as $i => [$name, $icon]) {
            ActivityType::query()->updateOrCreate(['name' => $name], ['icon' => $icon, 'order' => $i + 1]);
        }

        foreach (self::ROOT_CATEGORIES as $i => [$name, $icon]) {
            RootCategory::query()->updateOrCreate(['name' => $name], ['icon' => $icon, 'order' => $i + 1]);
        }

        foreach (self::SALE_UNITS as [$name, $abbr, $factor]) {
            SaleUnit::query()->updateOrCreate(
                ['name' => $name],
                ['abbr' => $abbr, 'default_factor' => $factor, 'status' => RefStatus::Active],
            );
        }

        foreach (self::EQUIPMENTS as $i => $name) {
            Equipment::query()->updateOrCreate(['name' => $name], ['order' => $i + 1]);
        }

        $this->seedCurrencies();
    }

    /**
     * SYP is seeded as the base currency by the currencies migration. USD is the second
     * currency so the FX screens have a pair to show, with one open-ended rate.
     */
    private function seedCurrencies(): void
    {
        $syp = Currency::query()->where('iso', 'SYP')->firstOrFail();

        $usd = Currency::query()->firstOrCreate(
            ['iso' => 'USD'],
            ['name' => 'الدولار الأمريكي', 'symbol' => '$', 'decimals' => 2],
        );

        $exists = FxRate::query()
            ->where('from_currency_id', $usd->id)
            ->where('to_currency_id', $syp->id)
            ->whereNull('effective_to')
            ->exists();

        if (! $exists) {
            FxRate::query()->create([
                'from_currency_id' => $usd->id,
                'to_currency_id' => $syp->id,
                'rate' => self::USD_TO_SYP,
                'effective_from' => now()->subMonth()->startOfDay(),
                'effective_to' => null,
            ]);
        }
    }
}
