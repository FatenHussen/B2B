<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Modules\Identity\Domain\Models\WarehouseDevice;
use Modules\Tenancy\Domain\Enums\WarehouseStatus;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Modules\Tenancy\Domain\Models\Warehouse;
use Tests\Support\CatalogAssert;

it('logs in a pre-registered warehouse device', function () {
    $channel = SupplyChannel::factory()->create();
    $warehouse = Warehouse::query()->create([
        'channel_id' => $channel->id,
        'name' => 'مستودع المزة',
        'status' => WarehouseStatus::Active,
    ]);

    $plain = 'devtok_wh_mezzeh';
    WarehouseDevice::query()->create([
        'device_token_hash' => hash('sha256', $plain),
        'pin_hash' => Hash::make('4821'),
        'warehouse_id' => $warehouse->id,
        'channel_id' => $channel->id,
        'label' => 'mezzeh-scanner',
    ]);

    $response = $this->postJson('/api/v1/warehouse/auth/device-login', [
        'device_token' => $plain,
        'pin' => '4821',
    ]);

    CatalogAssert::ok($response, ['token', 'warehouse']);
    expect($response->json('data.warehouse.id'))->toBe($warehouse->id)
        ->and($response->json('data.warehouse.name'))->toBe('مستودع المزة');
});

it('rejects an unknown device', function () {
    CatalogAssert::error(
        $this->postJson('/api/v1/warehouse/auth/device-login', [
            'device_token' => 'unknown',
            'pin' => '4821',
        ]),
        401,
        'unauthenticated',
    );
});
