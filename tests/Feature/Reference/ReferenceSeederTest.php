<?php

declare(strict_types=1);

use Modules\Reference\Database\Seeders\ReferenceSeeder;
use Modules\Reference\Domain\Enums\ZoneStatus;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\Zone;

it('seeds the fourteen governorates, each with zones', function () {
    $this->seed(ReferenceSeeder::class);

    expect(Governorate::query()->count())->toBe(14)
        ->and(Governorate::query()->where('code', 'DI')->value('name_ar'))->toBe('دمشق')
        ->and(Governorate::query()->whereDoesntHave('zones')->count())->toBe(0)
        ->and(Zone::query()->count())->toBeGreaterThan(14);
});

it('is idempotent and leaves a disabled zone disabled', function () {
    $this->seed(ReferenceSeeder::class);

    $governorates = Governorate::query()->count();
    $zones = Zone::query()->count();

    $zone = Zone::query()->where('name', 'المزة')->firstOrFail();
    $zone->forceFill(['status' => ZoneStatus::Inactive])->save();

    $this->seed(ReferenceSeeder::class);

    expect(Governorate::query()->count())->toBe($governorates)
        ->and(Zone::query()->count())->toBe($zones)
        ->and($zone->refresh()->status)->toBe(ZoneStatus::Inactive);
});
