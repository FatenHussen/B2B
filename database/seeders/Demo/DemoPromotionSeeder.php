<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use Modules\Promotion\Domain\Enums\OfferStatus;
use Modules\Promotion\Domain\Enums\OfferType;
use Modules\Promotion\Domain\Enums\TargetingScope;
use Modules\Promotion\Domain\Models\Offer;
use Modules\Promotion\Domain\Models\OfferActivityType;
use Modules\Promotion\Domain\Models\OfferComponent;
use Modules\Promotion\Domain\Models\OfferRedemption;
use Modules\Promotion\Domain\Models\OfferReward;
use Modules\Promotion\Domain\Models\OfferZone;

/**
 * One offer per type, in the statuses the offers screen filters by.
 *
 * `components` name the products a line must contain for the offer to apply and
 * `rewards` what it gives; `EloquentOfferApplicator` reads `rules.buy_qty`/`get_qty`
 * for buy-x-get-y and the first reward's discount for the rest.
 */
final class DemoPromotionSeeder extends DemoSeeder
{
    /**
     * @var list<array{name: string, type: string, status: string, description: string, stackable: bool, priority: int, starts: int, ends: int|null, total_qty: int|null, per_retailer: int|null, per_order: int|null, min_invoice: int, min_items: int|null, scope: string, rules: array<string, int>|null, components: array<string, int>, reward: array{product?: string, qty?: int, percent?: int, amount?: int}, zones?: list<array{0: string, 1: string}>, activities?: list<string>, redeemed?: array{0: int, 1: int}, stop_reason?: string}>
     */
    private const OFFERS = [
        [
            'name' => 'خصم 10% على زيت الدرة 1 ل',
            'type' => 'product_discount',
            'status' => 'active',
            'description' => 'خصم مباشر على كل عبوة زيت دوار الشمس 1 ل خلال الشهر',
            'stackable' => false,
            'priority' => 10,
            'starts' => -7,
            'ends' => 21,
            'total_qty' => 2_000,
            'per_retailer' => 120,
            'per_order' => 60,
            'min_invoice' => 0,
            'min_items' => null,
            'scope' => 'all',
            'rules' => null,
            'components' => ['OIL-1L' => 1],
            'reward' => ['product' => 'OIL-1L', 'qty' => 1, 'percent' => 10],
            'redeemed' => [37, 412],
        ],
        [
            'name' => 'اشترِ 10 سكر واحصل على 1 مجاناً',
            'type' => 'buy_x_get_y',
            'status' => 'active',
            'description' => 'على كل 10 أكياس سكر أبيض 1 كغ كيس إضافي مجاناً',
            'stackable' => false,
            'priority' => 20,
            'starts' => -14,
            'ends' => 30,
            'total_qty' => 500,
            'per_retailer' => 10,
            'per_order' => 5,
            'min_invoice' => 0,
            'min_items' => 10,
            'scope' => 'all',
            'rules' => ['buy_qty' => 10, 'get_qty' => 1],
            'components' => ['SUG-1KG' => 10],
            'reward' => ['product' => 'SUG-1KG', 'qty' => 1],
            'redeemed' => [58, 58],
        ],
        [
            'name' => 'خصم 25,000 ل.س على فواتير فوق 500,000',
            'type' => 'invoice_discount',
            'status' => 'active',
            'description' => 'خصم ثابت على الفاتورة عند تجاوز الحد الأدنى',
            'stackable' => true,
            'priority' => 5,
            'starts' => -3,
            'ends' => 27,
            'total_qty' => null,
            'per_retailer' => 4,
            'per_order' => 1,
            'min_invoice' => 500_000,
            'min_items' => null,
            'scope' => 'all',
            'rules' => null,
            'components' => [],
            'reward' => ['amount' => 25_000],
            'redeemed' => [12, 12],
        ],
        [
            'name' => 'باقة الإفطار',
            'type' => 'bundle',
            'status' => 'active',
            'description' => 'جبنة بيضاء + لبنة + حليب بسعر الباقة',
            'stackable' => false,
            'priority' => 15,
            'starts' => -10,
            'ends' => 20,
            'total_qty' => 300,
            'per_retailer' => 20,
            'per_order' => 10,
            'min_invoice' => 0,
            'min_items' => 3,
            'scope' => 'zones',
            'rules' => null,
            'components' => ['CHZ-500' => 1, 'LAB-500' => 1, 'MILK-1L' => 1],
            'reward' => ['percent' => 8],
            'zones' => [['DI', 'المزة'], ['DI', 'المالكي'], ['DI', 'كفرسوسة'], ['DI', 'الميدان']],
            'redeemed' => [21, 63],
        ],
        [
            'name' => 'خصم متدرج على الشاي',
            'type' => 'tiered_discount',
            'status' => 'draft',
            'description' => 'كلما زادت الكمية زاد الخصم - قيد التحضير',
            'stackable' => false,
            'priority' => 8,
            'starts' => 7,
            'ends' => 37,
            'total_qty' => null,
            'per_retailer' => null,
            'per_order' => null,
            'min_invoice' => 0,
            'min_items' => 6,
            'scope' => 'all',
            'rules' => null,
            'components' => ['TEA-500' => 6],
            'reward' => ['product' => 'TEA-500', 'qty' => 6, 'percent' => 5],
        ],
        [
            'name' => 'هدية شيبس مع كل كرتونة كولا',
            'type' => 'gift',
            'status' => 'active',
            'description' => 'كيس شيبس ذرة مجاناً مع كل 24 عبوة كوكاكولا',
            'stackable' => true,
            'priority' => 12,
            'starts' => -5,
            'ends' => 25,
            'total_qty' => 400,
            'per_retailer' => 8,
            'per_order' => 4,
            'min_invoice' => 0,
            'min_items' => 24,
            'scope' => 'all',
            'rules' => ['buy_qty' => 24, 'get_qty' => 1],
            'components' => ['COLA-330' => 24],
            'reward' => ['product' => 'CHIPS-50', 'qty' => 1],
            'activities' => ['سوبر ماركت', 'بقالة'],
            'redeemed' => [44, 44],
        ],
        [
            'name' => 'خصم الصيف على العصائر',
            'type' => 'product_discount',
            'status' => 'expired',
            'description' => 'حملة صيفية انتهت',
            'stackable' => false,
            'priority' => 3,
            'starts' => -90,
            'ends' => -30,
            'total_qty' => 1_000,
            'per_retailer' => 50,
            'per_order' => 24,
            'min_invoice' => 0,
            'min_items' => null,
            'scope' => 'all',
            'rules' => null,
            'components' => ['JUICE-ORG-1L' => 1],
            'reward' => ['product' => 'JUICE-ORG-1L', 'qty' => 1, 'percent' => 15],
            'redeemed' => [188, 1_000],
        ],
        [
            'name' => 'خصم المنظفات',
            'type' => 'product_discount',
            'status' => 'stopped',
            'description' => 'أوقف بعد نفاد الكمية المخصصة',
            'stackable' => false,
            'priority' => 4,
            'starts' => -20,
            'ends' => 10,
            'total_qty' => 100,
            'per_retailer' => 10,
            'per_order' => 4,
            'min_invoice' => 0,
            'min_items' => null,
            'scope' => 'all',
            'rules' => null,
            'components' => ['DET-3KG' => 1],
            'reward' => ['product' => 'DET-3KG', 'qty' => 1, 'amount' => 5_000],
            'redeemed' => [100, 100],
            'stop_reason' => 'نفاد الكمية المخصصة للعرض',
        ],
    ];

    public function run(): void
    {
        foreach (self::OFFERS as $row) {
            $offer = Offer::query()->firstOrCreate(
                ['name' => $row['name']],
                [
                    'type' => OfferType::from($row['type']),
                    'description' => $row['description'],
                    'stackable' => $row['stackable'],
                    'priority' => $row['priority'],
                    'starts_at' => now()->addDays($row['starts'])->startOfDay(),
                    'ends_at' => $row['ends'] !== null ? now()->addDays($row['ends'])->endOfDay() : null,
                    'total_qty' => $row['total_qty'],
                    'per_retailer_max' => $row['per_retailer'],
                    'per_order_max' => $row['per_order'],
                    'min_invoice_value' => $row['min_invoice'],
                    'min_items' => $row['min_items'],
                    'targeting_scope' => TargetingScope::from($row['scope']),
                    'rules' => $row['rules'],
                    'stop_reason' => $row['stop_reason'] ?? null,
                ],
            );
            if ($offer->wasRecentlyCreated || $offer->status === null) {
                $offer->forceFill(['status' => OfferStatus::from($row['status'])])->save();
            }

            foreach ($row['components'] as $sku => $qty) {
                OfferComponent::query()->firstOrCreate(
                    ['offer_id' => $offer->id, 'product_id' => $this->productId($sku)],
                    ['qty' => $qty],
                );
            }

            $reward = $row['reward'];
            OfferReward::query()->firstOrCreate(
                ['offer_id' => $offer->id],
                [
                    'product_id' => isset($reward['product']) ? $this->productId($reward['product']) : null,
                    'qty' => $reward['qty'] ?? 1,
                    'discount_percent' => $reward['percent'] ?? null,
                    'discount_amount' => $reward['amount'] ?? null,
                ],
            );

            foreach ($row['zones'] ?? [] as [$governorate, $zone]) {
                OfferZone::query()->firstOrCreate([
                    'offer_id' => $offer->id,
                    'zone_id' => $this->zoneId($governorate, $zone),
                ]);
            }

            foreach ($row['activities'] ?? [] as $name) {
                OfferActivityType::query()->firstOrCreate([
                    'offer_id' => $offer->id,
                    'activity_type_id' => $this->activityTypeId($name),
                ]);
            }

            if (isset($row['redeemed'])) {
                [$applied, $consumed] = $row['redeemed'];
                OfferRedemption::query()->firstOrCreate(
                    ['offer_id' => $offer->id],
                    ['applied_count' => $applied, 'qty_consumed' => $consumed],
                );
            }
        }
    }
}
