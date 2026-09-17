<?php

declare(strict_types=1);

/**
 * BE-R10 — EP-PB-001 `GET /public/refs`.
 */

use Illuminate\Support\Carbon;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Reference\Database\Seeders\ReferenceSeeder;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\ActivityType;
use Modules\Reference\Domain\Models\Equipment;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\RootCategory;
use Modules\Reference\Domain\Models\SaleUnit;
use Modules\Reference\Domain\Models\Zone;
use Modules\Tenancy\Domain\Models\SupplyChannel;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

it('returns the six active entities in one payload without a Bearer', function () {
    $gov = Governorate::factory()->create();
    $zone = Zone::factory()->create(['governorate_id' => $gov->id]);
    $disabledZone = Zone::factory()->create(['governorate_id' => $gov->id, 'status' => 'inactive']);
    $root = RootCategory::query()->create(['name' => 'غذائية']);
    $activity = ActivityType::query()->create(['name' => 'بقالة']);
    $activity->suggestedCategories()->sync([$root->id]);
    SaleUnit::query()->create(['name' => 'قطعة']);
    Equipment::query()->create(['name' => 'ثلاجة']);

    $response = $this->getJson('/api/v1/public/refs')->assertOk();

    $response->assertJsonCount(1, 'data.governorates')
        ->assertJsonCount(1, 'data.zones')
        ->assertJsonPath('data.zones.0.id', $zone->id)
        ->assertJsonPath('data.activity_types.0.suggested_category_ids', [$root->id])
        ->assertJsonCount(1, 'data.root_categories')
        ->assertJsonCount(1, 'data.sale_units')
        ->assertJsonCount(1, 'data.equipments');

    expect(collect((array) $response->json('data.zones'))->pluck('id')->all())->not->toContain($disabledZone->id)
        ->and($response->json('meta.sync_cursor'))->toStartWith('c_')
        ->and($response->json('data.sync_cursor'))->toBe($response->json('meta.sync_cursor'));
});

it('a since request returns only what changed', function () {
    // BE-R10 acceptance criterion 1.
    Carbon::setTestNow('2026-09-17 09:00:00');
    Governorate::factory()->create(['name_ar' => 'قديمة']);
    $unchanged = ActivityType::query()->create(['name' => 'قديم']);

    $cursor = $this->getJson('/api/v1/public/refs')->assertOk()->json('meta.sync_cursor');

    Carbon::setTestNow('2026-09-17 10:00:00');
    $new = Governorate::factory()->create(['name_ar' => 'جديدة']);
    $unchanged->refresh();

    $diff = $this->getJson('/api/v1/public/refs?since='.$cursor)->assertOk();

    $diff->assertJsonCount(1, 'data.governorates')
        ->assertJsonPath('data.governorates.0.id', $new->id)
        ->assertJsonCount(0, 'data.activity_types');

    Carbon::setTestNow();
});

it('a since request carries a disabled row so the device can drop it', function () {
    Carbon::setTestNow('2026-09-17 09:00:00');
    $unit = SaleUnit::query()->create(['name' => 'كرتون']);
    $cursor = $this->getJson('/api/v1/public/refs')->json('meta.sync_cursor');

    Carbon::setTestNow('2026-09-17 10:00:00');
    $unit->status = RefStatus::Disabled;
    $unit->save();

    $this->getJson('/api/v1/public/refs?since='.$cursor)
        ->assertOk()
        ->assertJsonCount(1, 'data.sale_units')
        ->assertJsonPath('data.sale_units.0.status', 'disabled');

    Carbon::setTestNow();
});

it('leaks no channel data under any parameter', function () {
    // BE-R10 acceptance criterion 3, REQ-IN-06. Channels exist; the payload never
    // names one — not as a key, not as a value.
    SupplyChannel::factory()->create(['name' => 'شركة النور الفريدة']);
    Governorate::factory()->create();

    foreach (['', '?since=', '?since=c_20000101000000', '?include=channels', '?filter[channel_id]=1', '?channels=1'] as $qs) {
        $body = $this->getJson('/api/v1/public/refs'.$qs)->assertOk()->getContent();

        expect($body)->not->toContain('channel')
            ->and($body)->not->toContain('شركة النور الفريدة');
    }
});

it('keeps the payload small enough for a low-end device on 3G', function () {
    // BE-R10 acceptance criterion 2. The seeder's fourteen governorates and ~80 zones plus
    // a working set of the rest must fit well inside one 3G round trip: under 32 KB.
    $this->seed(ReferenceSeeder::class);
    foreach (range(1, 10) as $i) {
        ActivityType::query()->create(['name' => "نشاط {$i}"]);
        RootCategory::query()->create(['name' => "فئة {$i}"]);
        SaleUnit::query()->create(['name' => "وحدة {$i}"]);
        Equipment::query()->create(['name' => "تجهيز {$i}"]);
    }

    $bytes = strlen($this->getJson('/api/v1/public/refs')->assertOk()->getContent());

    expect($bytes)->toBeLessThan(32 * 1024);
});
