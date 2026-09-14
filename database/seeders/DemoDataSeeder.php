<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Demo\DemoCatalogSeeder;
use Database\Seeders\Demo\DemoChannelSeeder;
use Database\Seeders\Demo\DemoInventorySeeder;
use Database\Seeders\Demo\DemoOrderingSeeder;
use Database\Seeders\Demo\DemoPeopleSeeder;
use Database\Seeders\Demo\DemoPricingSeeder;
use Database\Seeders\Demo\DemoPromotionSeeder;
use Database\Seeders\Demo\DemoReferenceSeeder;
use Illuminate\Database\Seeder;
use Modules\Core\Support\Tenant;
use Modules\Tenancy\Domain\Models\SupplyChannel;

/**
 * Fills the demo channel with working data for every dashboard screen.
 *
 * Runs as the demo channel's tenant, so every channel-scoped model created below lands on
 * that channel without a seeder naming it. The order matters: each seeder looks up rows
 * the one before it wrote — a base price needs its product, a sub-order its retailer and
 * prices, a reservation its sub-order.
 *
 * Safe to run twice: every seeder matches on a natural key and only writes what is
 * missing, and `DatabaseSeeder` calls this only outside production. Run on its own with
 * `php artisan db:seed --class=DemoDataSeeder` (after `db:seed`, which creates the channel).
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
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
        });
    }
}
