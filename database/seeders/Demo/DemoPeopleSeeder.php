<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use Modules\Identity\Domain\Enums\AppUserKind;
use Modules\Identity\Domain\Enums\ProfileStatus;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\RepDutyState;
use Modules\Identity\Domain\Models\RepProfile;
use Modules\Identity\Domain\Models\RepProfileZone;
use Modules\Identity\Domain\Models\RetailerProfile;
use Modules\Identity\Domain\Models\RetailerProfileCategory;
use Modules\Identity\Domain\Models\RetailerProfileEquipment;
use Modules\Pricing\Domain\Models\RepCommercialLimit;
use Modules\Reference\Domain\Models\Equipment;
use Modules\Reference\Domain\Models\Zone;

/**
 * Retailers and reps inside the demo channel's coverage.
 *
 * Retailers are platform-wide (a shop is not owned by a channel) but every one here sits
 * in a zone the channel covers, so its orders route to this channel. Reps belong to the
 * channel through `rep_profiles.channel_id`; two are on duty so assignment works.
 */
final class DemoPeopleSeeder extends DemoSeeder
{
    /**
     * @var list<array{phone: string, owner: string, shop: string, activity: string, gov: string, zone: string, lat: float, lng: float, address: string, categories: list<string>, equipments: list<string>, status: string}>
     */
    private const RETAILERS = [
        ['phone' => '+963931000001', 'owner' => 'أبو محمد', 'shop' => 'سوبر ماركت الأمانة', 'activity' => 'سوبر ماركت', 'gov' => 'DI', 'zone' => 'المزة', 'lat' => 33.5030, 'lng' => 36.2530, 'address' => 'المزة - شارع الجلاء - بناء 12', 'categories' => ['مواد غذائية', 'مشروبات', 'ألبان وأجبان', 'منظفات', 'عناية شخصية', 'حلويات ومقرمشات'], 'equipments' => ['براد عرض', 'فريزر', 'رفوف عرض', 'صندوق دفع'], 'status' => 'active'],
        ['phone' => '+963931000002', 'owner' => 'سامر حداد', 'shop' => 'بقالة النور', 'activity' => 'بقالة', 'gov' => 'DI', 'zone' => 'الميدان', 'lat' => 33.4930, 'lng' => 36.3010, 'address' => 'الميدان - جادة الزهور', 'categories' => ['مواد غذائية', 'مشروبات', 'حلويات ومقرمشات'], 'equipments' => ['براد عرض', 'رفوف عرض'], 'status' => 'active'],
        ['phone' => '+963931000003', 'owner' => 'رنا خليل', 'shop' => 'كافيه لاتيه', 'activity' => 'كافيه', 'gov' => 'DI', 'zone' => 'المالكي', 'lat' => 33.5170, 'lng' => 36.2740, 'address' => 'المالكي - شارع عبد المنعم رياض', 'categories' => ['مشروبات', 'ألبان وأجبان', 'حلويات ومقرمشات'], 'equipments' => ['براد عرض'], 'status' => 'active'],
        ['phone' => '+963931000004', 'owner' => 'خالد عثمان', 'shop' => 'مطعم الشام', 'activity' => 'مطعم', 'gov' => 'DI', 'zone' => 'باب توما', 'lat' => 33.5140, 'lng' => 36.3170, 'address' => 'باب توما - ساحة الكنيسة', 'categories' => ['مواد غذائية', 'مشروبات', 'ألبان وأجبان', 'منظفات'], 'equipments' => ['فريزر', 'ميزان إلكتروني'], 'status' => 'active'],
        ['phone' => '+963931000005', 'owner' => 'ليلى صالح', 'shop' => 'ماركت الياسمين', 'activity' => 'سوبر ماركت', 'gov' => 'RD', 'zone' => 'جرمانا', 'lat' => 33.4860, 'lng' => 36.3450, 'address' => 'جرمانا - الشارع العام', 'categories' => ['مواد غذائية', 'مشروبات', 'ألبان وأجبان', 'منظفات', 'عناية شخصية'], 'equipments' => ['براد عرض', 'فريزر', 'رفوف عرض'], 'status' => 'active'],
        ['phone' => '+963931000006', 'owner' => 'فادي نصر', 'shop' => 'بقالة الحي', 'activity' => 'بقالة', 'gov' => 'RD', 'zone' => 'صحنايا', 'lat' => 33.4230, 'lng' => 36.2200, 'address' => 'صحنايا - دوار البلدية', 'categories' => ['مواد غذائية', 'مشروبات'], 'equipments' => ['رفوف عرض'], 'status' => 'active'],
        ['phone' => '+963931000007', 'owner' => 'محمد الحلبي', 'shop' => 'سوبر ماركت الشهباء', 'activity' => 'سوبر ماركت', 'gov' => 'HL', 'zone' => 'الحمدانية', 'lat' => 36.1830, 'lng' => 37.1150, 'address' => 'الحمدانية - الدور الأول', 'categories' => ['مواد غذائية', 'مشروبات', 'ألبان وأجبان', 'منظفات', 'عناية شخصية', 'حلويات ومقرمشات'], 'equipments' => ['براد عرض', 'فريزر', 'رفوف عرض', 'ميزان إلكتروني', 'صندوق دفع'], 'status' => 'active'],
        ['phone' => '+963931000008', 'owner' => 'هبة عيسى', 'shop' => 'حلويات الفردوس', 'activity' => 'محل حلويات', 'gov' => 'DI', 'zone' => 'القصاع', 'lat' => 33.5190, 'lng' => 36.3090, 'address' => 'القصاع - شارع الفردوس', 'categories' => ['ألبان وأجبان', 'حلويات ومقرمشات'], 'equipments' => ['براد عرض'], 'status' => 'pending_review'],
    ];

    /**
     * @var list<array{phone: string, name: string, activity: string, zones: list<array{0: string, 1: string}>, on_duty: bool, discount: int, cash_hold: int}>
     */
    private const REPS = [
        ['phone' => '+963932000001', 'name' => 'عمر الشامي', 'activity' => 'سوبر ماركت', 'zones' => [['DI', 'المزة'], ['DI', 'المالكي'], ['DI', 'كفرسوسة']], 'on_duty' => true, 'discount' => 10, 'cash_hold' => 5_000_000],
        ['phone' => '+963932000002', 'name' => 'ياسر حمود', 'activity' => 'بقالة', 'zones' => [['DI', 'الميدان'], ['DI', 'باب توما'], ['DI', 'القصاع'], ['DI', 'برزة'], ['RD', 'جرمانا']], 'on_duty' => true, 'discount' => 5, 'cash_hold' => 2_000_000],
        ['phone' => '+963932000003', 'name' => 'نور الدين حلبي', 'activity' => 'سوبر ماركت', 'zones' => [['HL', 'الحمدانية'], ['HL', 'حلب الجديدة']], 'on_duty' => false, 'discount' => 15, 'cash_hold' => 3_000_000],
    ];

    public function run(): void
    {
        $this->seedRetailers();
        $this->seedReps();
    }

    private function seedRetailers(): void
    {
        foreach (self::RETAILERS as $row) {
            $user = AppUser::query()->firstOrCreate(
                ['phone' => $row['phone']],
                ['name' => $row['owner'], 'kind' => AppUserKind::Retailer, 'status' => UserStatus::Active],
            );

            $zoneId = $this->zoneId($row['gov'], $row['zone']);

            $profile = RetailerProfile::query()->firstOrCreate(
                ['app_user_id' => $user->id],
                [
                    'shop_name' => $row['shop'],
                    'activity_type_id' => $this->activityTypeId($row['activity']),
                    'governorate_id' => (int) Zone::query()->findOrFail($zoneId)->governorate_id,
                    'zone_id' => $zoneId,
                    'lat' => $row['lat'],
                    'lng' => $row['lng'],
                    'address' => $row['address'],
                    'status' => ProfileStatus::from($row['status']),
                ],
            );

            foreach ($row['categories'] as $name) {
                RetailerProfileCategory::query()->firstOrCreate([
                    'retailer_profile_id' => $profile->id,
                    'root_category_id' => $this->rootCategoryId($name),
                ]);
            }

            foreach ($row['equipments'] as $name) {
                RetailerProfileEquipment::query()->firstOrCreate([
                    'retailer_profile_id' => $profile->id,
                    'equipment_id' => (int) Equipment::query()->where('name', $name)->firstOrFail()->id,
                ]);
            }
        }
    }

    private function seedReps(): void
    {
        foreach (self::REPS as $row) {
            $user = AppUser::query()->firstOrCreate(
                ['phone' => $row['phone']],
                ['name' => $row['name'], 'kind' => AppUserKind::Rep, 'status' => UserStatus::Active],
            );

            $profile = RepProfile::query()->firstOrCreate(
                ['app_user_id' => $user->id],
                [
                    'channel_id' => $this->channelId(),
                    'activity_type_id' => $this->activityTypeId($row['activity']),
                    'status' => ProfileStatus::Active,
                ],
            );

            foreach ($row['zones'] as [$governorate, $zone]) {
                RepProfileZone::query()->firstOrCreate([
                    'rep_profile_id' => $profile->id,
                    'zone_id' => $this->zoneId($governorate, $zone),
                ]);
            }

            RepDutyState::query()->updateOrCreate(
                ['rep_user_id' => $user->id],
                ['on_duty' => $row['on_duty'], 'tracking_enabled' => $row['on_duty'], 'updated_at' => now()],
            );

            // rep_id is the app user id — the same id AssignSubOrders and
            // PUT /channel/reps/{id}/discount-cap are called with.
            RepCommercialLimit::query()->firstOrCreate(
                ['rep_id' => $user->id],
                ['max_discount_percent' => $row['discount'], 'max_cash_hold' => $row['cash_hold']],
            );
        }
    }
}
