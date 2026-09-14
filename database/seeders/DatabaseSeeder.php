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
use Modules\Tenancy\Domain\Models\SupplyChannel;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesPermissionsSeeder::class);
        $this->call(ReferenceSeeder::class);

        $channel = SupplyChannel::query()->firstOrCreate(
            ['slug' => 'demo-channel'],
            [
                'name' => 'Demo Channel',
                'legal_name' => 'Demo Channel LLC',
                'phone' => '+963911000000',
                'status' => 'active',
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
    }
}
