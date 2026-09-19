<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Demo\DemoCatalogSeeder;
use Database\Seeders\Demo\DemoChannelSeeder;
use Database\Seeders\Demo\DemoInventorySeeder;
use Database\Seeders\Demo\DemoOrderHistorySeeder;
use Database\Seeders\Demo\DemoOrderingSeeder;
use Database\Seeders\Demo\DemoPeopleSeeder;
use Database\Seeders\Demo\DemoPlatformSeeder;
use Database\Seeders\Demo\DemoPricingSeeder;
use Database\Seeders\Demo\DemoPromotionSeeder;
use Database\Seeders\Demo\DemoReferenceSeeder;
use Illuminate\Database\Seeder;
use Laravel\Telescope\Telescope;
use Modules\Core\Support\Tenant;
use Modules\Tenancy\Domain\Models\SupplyChannel;

/**
 * Fills the demo channel with working data for every dashboard screen, then the platform
 * with the other channels its back office lists.
 *
 * The first block runs as the demo channel's tenant, so every channel-scoped model created
 * there lands on that channel without a seeder naming it. The order matters: each seeder
 * looks up rows the one before it wrote — a base price needs its product, a sub-order its
 * retailer and prices, a reservation its sub-order. `DemoOrderHistorySeeder` comes last
 * and adds ninety days of volume behind the hand-picked queue.
 *
 * `DemoPlatformSeeder` runs outside any tenant and sets its own per channel: it is the
 * population behind the platform's channel list, usage series and audit log.
 *
 * Safe to run twice: every seeder matches on a natural key and only writes what is
 * missing, and `DatabaseSeeder` calls this only outside production. Run on its own with
 * `php artisan db:seed --class=DemoDataSeeder` (after `db:seed`, which creates the channel).
 */
class DemoDataSeeder extends Seeder
{
    /** Weekday orders the demo channel settles into by the end of the window. */
    private const DEMO_ORDERS_PER_DAY = 5;

    public function run(): void
    {
        // Telescope holds every query of a console command in memory until it ends; the
        // order history alone is tens of thousands, which is past the 128M limit.
        if (class_exists(Telescope::class)) {
            Telescope::stopRecording();
        }

        $channel = SupplyChannel::query()->where('slug', 'demo-channel')->firstOrFail();

        Tenant::as((int) $channel->id, function (): void {
            $this->call([
                DemoReferenceSeeder::class,
                DemoChannelSeeder::class,
                DemoPeopleSeeder::class,
                DemoCatalogSeeder::class,
                DemoPricingSeeder::class,
                DemoPromotionSeeder::class,
                DemoOrderingSeeder::class,
                DemoInventorySeeder::class,
            ]);
            $this->call(DemoOrderHistorySeeder::class, false, ['perDay' => self::DEMO_ORDERS_PER_DAY]);
        });

        $this->call(DemoPlatformSeeder::class);
    }
}
