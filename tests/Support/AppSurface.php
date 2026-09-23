<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Catalog\Domain\Enums\ProductStatus;
use Modules\Core\Domain\ValueObjects\Money;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Enums\ProfileStatus;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\RepProfile;
use Modules\Identity\Domain\Models\RepProfileZone;
use Modules\Identity\Domain\Models\RetailerProfile;
use Modules\Identity\Domain\Models\RetailerProfileCategory;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\ActivityType;
use Modules\Reference\Domain\Models\ChannelZone;
use Modules\Reference\Domain\Models\Currency;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\RootCategory;
use Modules\Reference\Domain\Models\SaleUnit;
use Modules\Reference\Domain\Models\Zone;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Tests\TestCase;

/**
 * Fixtures for the `/app/*` surface (BE-C12): a covered zone, a retailer in it, a rep on
 * the channel, and — the part every earlier fixture left out — a product **with a brand
 * and a category**, so the relation loads the routes actually make are actually made.
 *
 * A product created through the channel API has a price; one written straight to the
 * table would not, and the cart would fail on repricing instead of on the thing under
 * test. The channel-manager request that creates it is followed by `forgetGuards()`,
 * so the app request that comes next resolves its own user (CrossGuardTest hazard).
 */
final class AppSurface
{
    /**
     * @return array<string, mixed>
     */
    public static function refs(): array
    {
        $gov = Governorate::factory()->create();
        $zone = Zone::factory()->create(['governorate_id' => $gov->id, 'name' => 'المزة']);
        $otherZone = Zone::factory()->create(['governorate_id' => $gov->id, 'name' => 'برزة']);
        $activity = ActivityType::query()->create(['name' => 'بقالة', 'order' => 1, 'status' => RefStatus::Active]);
        $root = RootCategory::query()->create(['name' => 'غذائية', 'order' => 1, 'status' => RefStatus::Active]);
        $unit = SaleUnit::query()->create(['name' => 'قطعة', 'abbr' => 'pcs', 'default_factor' => 1, 'status' => RefStatus::Active]);
        $currency = Currency::query()->where('iso', 'SYP')->first()
            ?? Currency::query()->create(['iso' => 'SYP', 'name' => 'SYP', 'decimals' => 0]);

        return compact('gov', 'zone', 'otherZone', 'activity', 'root', 'unit', 'currency');
    }

    /**
     * An active channel covering `$zone`.
     *
     * @param  array<string, mixed>  $refs
     */
    public static function channel(array $refs, ?Zone $zone = null): SupplyChannel
    {
        $channel = SupplyChannel::factory()->create();
        $zone ??= $refs['zone'];
        Tenant::as($channel->id, fn () => ChannelZone::query()->create(['zone_id' => $zone->id]));

        return $channel;
    }

    /**
     * @param  array<string, mixed>  $refs
     */
    public static function retailer(array $refs, ?Zone $zone = null): AppUser
    {
        $user = AppUser::factory()->retailer()->create(['status' => UserStatus::Active]);
        $profile = RetailerProfile::query()->create([
            'app_user_id' => $user->id,
            'shop_name' => 'محل '.$user->id,
            'activity_type_id' => $refs['activity']->id,
            'governorate_id' => $refs['gov']->id,
            'zone_id' => ($zone ?? $refs['zone'])->id,
            'status' => ProfileStatus::Active,
        ]);
        RetailerProfileCategory::query()->create([
            'retailer_profile_id' => $profile->id,
            'root_category_id' => $refs['root']->id,
        ]);

        return $user->fresh(['retailerProfile.categories']) ?? $user;
    }

    public static function retailerId(AppUser $retailer): int
    {
        return (int) RetailerProfile::query()->where('app_user_id', $retailer->id)->value('id');
    }

    /**
     * @param  array<string, mixed>  $refs
     */
    public static function rep(SupplyChannel $channel, array $refs, ?Zone $zone = null): AppUser
    {
        $user = AppUser::factory()->rep()->create(['status' => UserStatus::Active]);
        $profile = RepProfile::query()->create([
            'app_user_id' => $user->id,
            'channel_id' => $channel->id,
            'activity_type_id' => $refs['activity']->id,
            'status' => ProfileStatus::Active,
        ]);
        RepProfileZone::query()->create(['rep_profile_id' => $profile->id, 'zone_id' => ($zone ?? $refs['zone'])->id]);

        return $user->fresh(['repProfile.zones']) ?? $user;
    }

    /**
     * A product with a brand and a category, created through the channel API so it is
     * priced. Returns [product_id, brand_id, category_id]. Leaves no guard user behind.
     *
     * @param  array<string, mixed>  $refs
     * @return array{0: int, 1: int, 2: int}
     */
    public static function productWithBrand(TestCase $t, SupplyChannel $channel, array $refs, string $sku = 'OIL-SUN-1L'): array
    {
        $manager = ChannelUser::factory()->forChannel($channel)->create();
        $manager->assignRole('channel_manager');
        Sanctum::actingAs($manager, ['*'], 'channel');

        $logo = $t->post('/api/v1/channel/media/upload', [
            'file' => \Illuminate\Http\UploadedFile::fake()->image('brand.jpg', 512, 512),
            'type' => 'image',
        ], ['X-Idempotency-Key' => 'appsurface-logo-'.uniqid()]);
        $brandId = (int) $t->postJson('/api/v1/channel/brands', [
            'name_ar' => 'ماركة '.$sku,
            'name_en' => 'Brand '.$sku,
            'logo' => (string) $logo->json('data.media_id'),
            'description' => 'وصف العلامة',
            'activity_type_ids' => [$refs['activity']->id],
        ])->json('data.id');
        $categoryId = (int) $t->postJson('/api/v1/channel/categories', [
            'name' => 'زيوت', 'parent_id' => $refs['root']->id, 'activity_type_ids' => [],
        ])->json('data.id');
        $productId = (int) $t->postJson('/api/v1/channel/products', [
            'name_ar' => 'زيت دوار الشمس', 'name_en' => 'Sunflower oil', 'sku' => $sku,
            'category_id' => $categoryId, 'brand_id' => $brandId,
            'status' => ProductStatus::Active->value, 'sale_unit_id' => $refs['unit']->id, 'min_order_qty' => 1,
            'pricing' => [
                'type' => 'tiered', 'base_price' => 12000, 'currency_id' => $refs['currency']->id,
                'tiers' => [['from' => 1, 'to' => null, 'price' => 12000]],
            ],
            'availability' => ['zone_ids' => [$refs['zone']->id], 'activity_type_ids' => [$refs['activity']->id], 'lead_time_days' => 2],
        ])->json('data.id');

        if ($productId === 0 || $brandId === 0 || $categoryId === 0) {
            throw new \RuntimeException('AppSurface::productWithBrand could not create the product through the channel API.');
        }

        app('auth')->forgetGuards();

        return [$productId, $brandId, $categoryId];
    }

    /**
     * One order with one sub-order for `$retailerId` on `$channel`, in `$status`, assigned
     * to `$repUserId` when given. Returns the sub-order id.
     *
     * @param  array<string, mixed>  $refs
     */
    public static function subOrder(SupplyChannel $channel, array $refs, int $retailerId, string $status = 'pending', ?int $repUserId = null, ?int $productId = null): int
    {
        static $n = 0;
        $n++;
        $orderId = (int) DB::table('orders')->insertGetId([
            'retailer_id' => $retailerId, 'source' => 'app', 'order_no' => "ORD-C12-{$n}", 'status' => $status,
            'currency' => 'SYP', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $id = (int) DB::table('sub_orders')->insertGetId([
            'order_id' => $orderId, 'channel_id' => $channel->id, 'retailer_id' => $retailerId,
            'zone_id' => $refs['zone']->id, 'source' => 'app', 'sub_order_no' => "SO-C12-{$n}", 'status' => $status,
            'subtotal' => 12000, 'discount' => 0, 'total' => 12000, 'rep_id' => $repUserId,
            'currency_code' => 'SYP', 'fx_rate' => Money::FX_UNIT,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('sub_order_lines')->insert([
            'sub_order_id' => $id, 'product_id' => $productId ?? 1, 'qty' => 1,
            'unit_price' => 12000, 'discount' => 0, 'line_total' => 12000,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * @return array<string, string>
     */
    public static function bearer(AppUser $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('surface', ['*'])->plainTextToken];
    }
}
