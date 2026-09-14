<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use Illuminate\Support\Carbon;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Pricing\Domain\Enums\AdjustmentMode;
use Modules\Pricing\Domain\Enums\PriceListStatus;
use Modules\Pricing\Domain\Enums\PriceListType;
use Modules\Pricing\Domain\Enums\PriceType;
use Modules\Pricing\Domain\Models\PriceChangeLog;
use Modules\Pricing\Domain\Models\PriceList;
use Modules\Pricing\Domain\Models\PriceListItem;
use Modules\Pricing\Domain\Models\PriceListZone;
use Modules\Pricing\Domain\Models\ProductBasePrice;
use Modules\Pricing\Domain\Models\ProductQtyTier;

/**
 * Base prices, quantity tiers, price lists and a price-change history.
 *
 * Every amount is an integer in the smallest unit of the base currency (rule 7). SYP has
 * zero decimals, so `12_000` is twelve thousand pounds — the same figure the product form
 * asks for "بأصغر وحدة عملة".
 */
final class DemoPricingSeeder extends DemoSeeder
{
    /**
     * SKU → base price, with optional tiers as `[from_qty, to_qty|null, price]`.
     *
     * @var array<string, array{price: int, tiers?: list<array{0: int, 1: int|null, 2: int}>}>
     */
    private const PRICES = [
        'SUG-1KG' => ['price' => 12_000, 'tiers' => [[1, 49, 12_000], [50, 99, 11_500], [100, null, 11_000]]],
        'RICE-5KG' => ['price' => 85_000],
        'OIL-1L' => ['price' => 38_000, 'tiers' => [[1, 23, 38_000], [24, 59, 36_500], [60, null, 35_000]]],
        'OIL-4L' => ['price' => 140_000],
        'TOM-400' => ['price' => 18_000],
        'TUNA-160' => ['price' => 27_000],
        'PASTA-500' => ['price' => 9_500],
        'TEA-500' => ['price' => 65_000],
        'NESC-200' => ['price' => 120_000, 'tiers' => [[1, 11, 120_000], [12, 47, 115_000], [48, null, 110_000]]],
        'COLA-330' => ['price' => 7_000],
        'PEPSI-1500' => ['price' => 14_000],
        'JUICE-ORG-1L' => ['price' => 16_000],
        'WATER-500' => ['price' => 24_000],
        'CHZ-500' => ['price' => 42_000],
        'YOG-1KG' => ['price' => 15_000],
        'LAB-500' => ['price' => 30_000],
        'MILK-1L' => ['price' => 21_000],
        'DET-3KG' => ['price' => 95_000],
        'FLC-1L' => ['price' => 22_000],
        'TP-100' => ['price' => 19_000],
        'SHP' => ['price' => 35_000],
        'CHOC-40' => ['price' => 6_500],
        'BIS-100' => ['price' => 4_500],
        'CHIPS-50' => ['price' => 3_500],
        'JUICE-MNG-1L' => ['price' => 17_000],
        'SNK-NEW' => ['price' => 5_000],
        'OLD-COLA-250' => ['price' => 6_000],
    ];

    /**
     * Price history for the change-log screen: `[sku, days ago, before, after, reason]`.
     * The last entry per SKU lands on today's base price so the log agrees with it.
     *
     * @var list<array{0: string, 1: int, 2: int, 3: int, 4: string}>
     */
    private const CHANGES = [
        ['SUG-1KG', 45, 10_500, 11_200, 'ارتفاع سعر الشراء'],
        ['SUG-1KG', 20, 11_200, 12_000, 'تحديث دوري'],
        ['OIL-1L', 30, 35_000, 38_000, 'ارتفاع سعر الشراء'],
        ['RICE-5KG', 25, 90_000, 85_000, 'عرض المورد'],
        ['NESC-200', 14, 125_000, 120_000, 'تعديل سعر الصرف'],
        ['COLA-330', 10, 6_500, 7_000, 'تحديث دوري'],
        ['DET-3KG', 7, 90_000, 95_000, 'ارتفاع سعر الشراء'],
        ['CHZ-500', 3, 40_000, 42_000, 'تحديث دوري'],
        ['MILK-1L', 1, 20_000, 21_000, 'ارتفاع سعر الشراء'],
    ];

    public function run(): void
    {
        $this->seedBasePrices();
        $this->seedPriceLists();
        $this->seedChangeLog();
    }

    private function seedBasePrices(): void
    {
        $currencyId = $this->baseCurrencyId();

        foreach (self::PRICES as $sku => $row) {
            $productId = $this->productId($sku);
            $tiers = $row['tiers'] ?? [];

            ProductBasePrice::query()->updateOrCreate(
                ['product_id' => $productId],
                [
                    'currency_id' => $currencyId,
                    'type' => $tiers === [] ? PriceType::Simple : PriceType::Tiered,
                    'base_price' => $row['price'],
                ],
            );

            foreach ($tiers as [$from, $to, $price]) {
                ProductQtyTier::query()->updateOrCreate(
                    ['product_id' => $productId, 'from_qty' => $from],
                    ['to_qty' => $to, 'price' => $price],
                );
            }
        }
    }

    /**
     * One list per type the engine applies — zone and retailer — plus the other statuses
     * the list screen filters by. A list with no items applies its adjustment to every
     * product; a list with items applies only to those, overriding where a price is set.
     */
    private function seedPriceLists(): void
    {
        $aleppo = PriceList::query()->firstOrCreate(
            ['name' => 'أسعار حلب'],
            [
                'type' => PriceListType::Zone,
                'status' => PriceListStatus::Active,
                'adjustment_mode' => AdjustmentMode::Percent,
                'adjustment_value' => 5,
                'effective_from' => now()->subWeeks(2)->startOfDay(),
                'effective_to' => null,
                'reason' => 'كلفة الشحن إلى حلب',
            ],
        );
        foreach ([['HL', 'الحمدانية'], ['HL', 'حلب الجديدة']] as [$governorate, $zone]) {
            PriceListZone::query()->firstOrCreate([
                'price_list_id' => $aleppo->id,
                'zone_id' => $this->zoneId($governorate, $zone),
            ]);
        }

        $keyAccount = PriceList::query()->firstOrCreate(
            ['name' => 'سوبر ماركت الأمانة - عميل رئيسي'],
            [
                'type' => PriceListType::Retailer,
                'status' => PriceListStatus::Active,
                'retailer_id' => $this->retailerProfileId('+963931000001'),
                'adjustment_mode' => AdjustmentMode::Percent,
                'adjustment_value' => -3,
                'effective_from' => now()->subMonth()->startOfDay(),
                'effective_to' => null,
                'reason' => 'اتفاقية حجم شراء',
            ],
        );
        foreach (['SUG-1KG' => 11_400, 'OIL-1L' => 36_000, 'RICE-5KG' => null, 'COLA-330' => null] as $sku => $override) {
            PriceListItem::query()->firstOrCreate(
                ['price_list_id' => $keyAccount->id, 'product_id' => $this->productId($sku)],
                ['override_price' => $override],
            );
        }

        PriceList::query()->firstOrCreate(
            ['name' => 'أسعار رمضان'],
            [
                'type' => PriceListType::Zone,
                'status' => PriceListStatus::Scheduled,
                'adjustment_mode' => AdjustmentMode::Percent,
                'adjustment_value' => -5,
                'effective_from' => now()->addMonth()->startOfDay(),
                'effective_to' => now()->addMonths(2)->startOfDay(),
                'reason' => 'حملة موسمية',
            ],
        );

        PriceList::query()->firstOrCreate(
            ['name' => 'أسعار الصيف'],
            [
                'type' => PriceListType::Zone,
                'status' => PriceListStatus::Expired,
                'adjustment_mode' => AdjustmentMode::Fixed,
                'adjustment_value' => -500,
                'effective_from' => now()->subMonths(4)->startOfDay(),
                'effective_to' => now()->subMonth()->startOfDay(),
                'reason' => 'حملة صيفية منتهية',
            ],
        );

        PriceList::query()->firstOrCreate(
            ['name' => 'قائمة تجريبية موقوفة'],
            [
                'type' => PriceListType::Group,
                'status' => PriceListStatus::Disabled,
                'group_id' => 1,
                'adjustment_mode' => AdjustmentMode::Percent,
                'adjustment_value' => -10,
                'reason' => 'أوقفت بعد التجربة',
            ],
        );
    }

    private function seedChangeLog(): void
    {
        $actorId = ChannelUser::query()->where('phone', '+963900000001')->value('id');

        foreach (self::CHANGES as [$sku, $daysAgo, $before, $after, $reason]) {
            $at = Carbon::now('Asia/Damascus')->subDays($daysAgo)->setTime(10, 30);

            PriceChangeLog::query()->firstOrCreate(
                ['product_id' => $this->productId($sku), 'at' => $at],
                [
                    'actor_user_id' => $actorId !== null ? (int) $actorId : null,
                    'before' => $before,
                    'after' => $after,
                    'reason' => $reason,
                ],
            );
        }
    }
}
