<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use Illuminate\Support\Facades\Hash;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\ChannelUserChannel;
use Modules\Identity\Domain\Models\WarehouseDevice;
use Modules\Identity\Domain\Models\WarehouseUser;
use Modules\Reference\Domain\Models\ChannelZone;
use Modules\Tenancy\Domain\Enums\WarehouseStatus;
use Modules\Tenancy\Domain\Models\ChannelLimit;
use Modules\Tenancy\Domain\Models\ChannelPlan;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Modules\Tenancy\Domain\Models\Warehouse;
use Spatie\Permission\PermissionRegistrar;

/**
 * The demo channel's own shape: settings, warehouses, coverage, staff and a warehouse device.
 *
 * Coverage is the list the retailer and rep seeders draw their zones from, so a retailer
 * seeded later always sits inside the channel's delivery area.
 */
final class DemoChannelSeeder extends DemoSeeder
{
    public const DEVICE_TOKEN = 'demo-warehouse-device';

    public const DEVICE_PIN = '1234';

    /**
     * Fees and minimums are decimal strings because `ChannelZone` stores them through
     * `MoneyCast`, exactly as `POST /channel/zones` does.
     *
     * @var array<string, array{fee: string, min: string|null, days: list<string>}>
     */
    private const COVERAGE = [
        'المزة' => ['fee' => '5000.00', 'min' => '150000.00', 'days' => ['sun', 'mon', 'tue', 'wed', 'thu']],
        'المالكي' => ['fee' => '5000.00', 'min' => '150000.00', 'days' => ['sun', 'mon', 'tue', 'wed', 'thu']],
        'كفرسوسة' => ['fee' => '5000.00', 'min' => '100000.00', 'days' => ['sun', 'tue', 'thu']],
        'الميدان' => ['fee' => '7500.00', 'min' => '100000.00', 'days' => ['sun', 'mon', 'tue', 'wed', 'thu', 'sat']],
        'باب توما' => ['fee' => '7500.00', 'min' => '100000.00', 'days' => ['mon', 'wed', 'sat']],
        'القصاع' => ['fee' => '7500.00', 'min' => '100000.00', 'days' => ['mon', 'wed', 'sat']],
        'برزة' => ['fee' => '10000.00', 'min' => '200000.00', 'days' => ['sun', 'tue', 'thu']],
        'جرمانا' => ['fee' => '12000.00', 'min' => '200000.00', 'days' => ['sun', 'tue', 'thu']],
        'صحنايا' => ['fee' => '15000.00', 'min' => '250000.00', 'days' => ['mon', 'thu']],
        'قدسيا' => ['fee' => '15000.00', 'min' => '250000.00', 'days' => ['mon', 'thu']],
        'الحمدانية' => ['fee' => '8000.00', 'min' => '150000.00', 'days' => ['sun', 'mon', 'tue', 'wed', 'thu']],
        'حلب الجديدة' => ['fee' => '8000.00', 'min' => '150000.00', 'days' => ['sun', 'tue', 'thu']],
    ];

    /** @var list<array{0: string, 1: string, 2: string, 3: string}> */
    private const STAFF = [
        ['+963900000002', 'سامر مدير المبيعات', 'sales@demo-channel.sy', 'sales_manager'],
        ['+963900000003', 'ريم مديرة الكتالوج', 'catalog@demo-channel.sy', 'catalog_manager'],
        ['+963900000004', 'وائل المحاسب', 'finance@demo-channel.sy', 'accountant'],
    ];

    public function run(): void
    {
        $this->seedSettings();
        $this->seedWarehouses();
        $this->seedCoverage();
        $this->seedStaff();
        $this->seedWarehouseDevice();
    }

    /**
     * Only fills what is still blank, so a channel someone has already edited through
     * `PUT /channel` keeps its values on a reseed.
     */
    private function seedSettings(): void
    {
        $channel = SupplyChannel::query()->findOrFail($this->channelId());

        $channel->update([
            'legal_name' => $channel->legal_name ?? 'شركة القناة التجريبية للتوزيع م.م',
            'tax_number' => $channel->tax_number ?? '011-2345678',
            'email' => $channel->email ?? 'ops@demo-channel.sy',
            // DatabaseSeeder creates the channel with no plan; the back office detail
            // shows a subscription only when there is one.
            'plan_id' => $channel->plan_id ?? ChannelPlan::query()->where('key', 'growth')->value('id'),
            'billing_cycle' => $channel->billing_cycle ?? 'yearly',
            'settings' => $channel->settings ?: [
                'locale' => 'ar',
                'timezone' => 'Asia/Damascus',
                'currency' => 'SYP',
                'order_cutoff_time' => '14:00',
                'delivery_window_days' => 2,
                'auto_confirm_orders' => false,
                'allow_backorders' => false,
                'support_phone' => '+963911000000',
            ],
        ]);

        // The limits row provisioning would have written from the plan (BE-T04), so the
        // detail's `limits` and the usage's `limit_usage` read the same numbers.
        $plan = ChannelPlan::query()->findOrFail($channel->fresh()->plan_id);
        ChannelLimit::query()->firstOrCreate(
            ['channel_id' => $channel->id],
            ['channel_id' => $channel->id] + $plan->limits,
        );
    }

    private function seedWarehouses(): void
    {
        $rows = [
            [self::WAREHOUSE_MAIN, 33.5138, 36.2765],
            [self::WAREHOUSE_ALEPPO, 36.2021, 37.1343],
        ];

        foreach ($rows as [$name, $lat, $lng]) {
            Warehouse::query()->firstOrCreate(
                ['name' => $name],
                ['status' => WarehouseStatus::Active, 'lat' => $lat, 'lng' => $lng],
            );
        }
    }

    private function seedCoverage(): void
    {
        foreach (self::COVERED_ZONES as [$governorate, $zone]) {
            $row = self::COVERAGE[$zone];

            ChannelZone::query()->firstOrCreate(
                ['zone_id' => $this->zoneId($governorate, $zone)],
                [
                    'delivery_days' => $row['days'],
                    'delivery_fee' => $row['fee'],
                    'min_order_value' => $row['min'],
                ],
            );
        }
    }

    private function seedStaff(): void
    {
        // Roles are seeded on team 0 by RolesPermissionsSeeder; syncRoles must look there.
        app(PermissionRegistrar::class)->setPermissionsTeamId(0);

        foreach (self::STAFF as [$phone, $name, $email, $role]) {
            $user = ChannelUser::query()->firstOrCreate(
                ['phone' => $phone],
                ['name' => $name, 'email' => $email, 'status' => UserStatus::Active],
            );

            ChannelUserChannel::query()->firstOrCreate(
                ['channel_user_id' => $user->id, 'channel_id' => $this->channelId()],
                ['is_default' => true],
            );

            $user->syncRoles([$role]);
        }
    }

    /**
     * One registered device on the main warehouse. Logs in through
     * `POST /warehouse/auth/device-login` with DEVICE_TOKEN and DEVICE_PIN.
     */
    private function seedWarehouseDevice(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(0);

        $keeper = WarehouseUser::query()->firstOrCreate(
            ['name' => 'أمين المستودع الرئيسي'],
            ['status' => UserStatus::Active],
        );
        $keeper->syncRoles(['warehouse_keeper']);

        WarehouseDevice::query()->firstOrCreate(
            ['device_token_hash' => hash('sha256', self::DEVICE_TOKEN)],
            [
                'pin_hash' => Hash::make(self::DEVICE_PIN),
                'warehouse_id' => $this->warehouseId(self::WAREHOUSE_MAIN),
                'channel_id' => $this->channelId(),
                'warehouse_user_id' => $keeper->id,
                'label' => 'جهاز المستودع الرئيسي',
            ],
        );
    }
}
