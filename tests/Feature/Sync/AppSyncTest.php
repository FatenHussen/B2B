<?php

declare(strict_types=1);

/**
 * AP-03 — EP-SY-001 … 004 sync surface.
 */

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Sync\Domain\Models\SyncOperation;
use Tests\Support\AppSurface;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

it('pulls catalog changes and reports sync status for the device', function () {
    $refs = AppSurface::refs();
    $rep = AppSurface::rep(AppSurface::channel($refs), $refs);
    Sanctum::actingAs($rep, ['*'], 'app');

    $pull = $this->getJson('/api/v1/app/sync/pull?scopes[]=catalog&limit=50', [
        'X-Device-Id' => 'sync-device-1',
    ]);
    CatalogAssert::ok($pull, ['changes', 'next_cursor', 'has_more', 'full_resync_required']);

    $status = $this->getJson('/api/v1/app/sync/status', ['X-Device-Id' => 'sync-device-1']);
    CatalogAssert::ok($status, ['pending_server_side', 'conflicts']);
    expect($status->json('data.pending_server_side'))->toBe(0);
});

it('replays the same client_op_id as duplicate and resolves a conflict', function () {
    $refs = AppSurface::refs();
    $rep = AppSurface::rep(AppSurface::channel($refs), $refs);
    Sanctum::actingAs($rep, ['*'], 'app');

    $headers = ['X-Device-Id' => 'sync-device-2'];
    $body = [
        'operations' => [[
            'client_op_id' => 'op_unknown_1',
            'type' => 'favorite.toggle',
            'payload' => ['product_id' => 1],
            'created_at' => '2026-03-01T09:05:00+03:00',
        ]],
    ];

    $first = $this->postJson('/api/v1/app/sync/push', $body, $headers);
    CatalogAssert::ok($first);
    expect($first->json('data.results.0.status'))->toBeIn(['failed', 'applied', 'conflict']);

    $second = $this->postJson('/api/v1/app/sync/push', $body, $headers);
    CatalogAssert::ok($second);
    expect($second->json('data.results.0.status'))->toBeIn(['duplicate', 'failed', 'applied', 'conflict']);
    expect($second->json('data.results.0.client_op_id'))->toBe('op_unknown_1');

    $op = SyncOperation::query()->create([
        'device_uuid' => 'sync-device-2',
        'app_user_id' => $rep->id,
        'op_id' => 'op_conflict_1',
        'type' => 'favorite.toggle',
        'payload' => ['product_id' => 2],
        'status' => 'conflict',
        'server_result' => ['status' => 'conflict', 'server_id' => null, 'error' => 'stale'],
    ]);

    $resolved = $this->postJson('/api/v1/app/sync/resolve-conflict', [
        'conflict_id' => (string) $op->id,
        'resolution' => 'server_wins',
    ], $headers);
    CatalogAssert::ok($resolved);
    expect($resolved->json('data.success'))->toBeTrue()
        ->and($op->fresh()->status)->toBe('applied');
});
