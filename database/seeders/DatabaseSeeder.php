<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\ChannelUserChannel;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Reference\Database\Seeders\ReferenceSeeder;
use Modules\Tenancy\Application\Services\ChannelLifecycle;
use Modules\Tenancy\Database\Seeders\ChannelPlanSeeder;
use Modules\Tenancy\Domain\Enums\ChannelStatus;
use Modules\Tenancy\Domain\Models\SupplyChannel;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesPermissionsSeeder::class);
        $this->call(ReferenceSeeder::class);
        $this->call(ChannelPlanSeeder::class);

        // No `status` here: the column is guarded (rule 8) and a new row starts in
        // `provisioning` since BE-T04. The demo channel is activated further down,
        // through the lifecycle, once the platform admin who does it exists.
        $channel = SupplyChannel::query()->firstOrCreate(
            ['slug' => 'demo-channel'],
            [
                'name' => 'Demo Channel',
                'legal_name' => 'Demo Channel LLC',
                'phone' => '+963911000000',
                'settings' => [],
            ],
        );

        $channelManager = ChannelUser::query()->firstOrCreate(
            ['phone' => '+963900000001'],
            [
                'name' => 'Channel Admin',
                'status' => UserStatus::Active,
            ],
        );
        ChannelUserChannel::query()->firstOrCreate(
            [
                'channel_user_id' => $channelManager->id,
                'channel_id' => $channel->id,
            ],
            ['is_default' => true],
        );
        $channelManager->syncRoles(['channel_manager']);

        $platformAdmin = PlatformUser::query()->firstOrCreate(
            ['email' => 'admin@platform.sy'],
            [
                'name' => 'Platform Admin',
                'phone' => '+963900000000',
                'password' => 'password',
                'status' => UserStatus::Active,
            ],
        );
        $platformAdmin->syncRoles(['platform_admin']);

        // The demo channel goes live the only way a channel does — provisioning → active
        // through ChannelLifecycle, with the seed's platform admin as the actor and a
        // channel_events row saying so. Idempotent: a channel already past provisioning
        // is left where it is.
        if ($channel->fresh()->status === ChannelStatus::Provisioning) {
            app(ChannelLifecycle::class)->transition($channel->fresh(), ChannelStatus::Active, $platformAdmin, 'seed: demo channel');
        }

        // Working data for every dashboard screen. Never in production: the VPS runs
        // `db:seed --force` for roles and reference rows only.
        if (! app()->isProduction()) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
