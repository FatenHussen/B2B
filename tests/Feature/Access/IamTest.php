<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Access\Domain\Models\AccessRole;
use Modules\Access\Domain\PermissionCatalog;
use Modules\Core\Domain\Models\AuditLog;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\PlatformUser;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function platformAdmin(): PlatformUser
{
    $user = PlatformUser::factory()->create();
    $user->assignRole('platform_admin');
    Sanctum::actingAs($user, ['*'], 'platform');

    return $user;
}

it('lists the catalog permissions for SP-02', function () {
    platformAdmin();

    $response = $this->getJson('/api/v1/platform/iam/permissions?filter[system]=platform&per_page=5');
    CatalogAssert::ok($response);
    expect($response->json('data.0.code'))->toStartWith('ad.')
        ->and($response->json('meta.per_page'))->toBe(5);
});

it('lists roles, sod rules, holders and the dual-approval inbox', function () {
    platformAdmin();

    CatalogAssert::ok($this->getJson('/api/v1/platform/iam/roles'));
    $sod = $this->getJson('/api/v1/platform/iam/sod-rules');
    CatalogAssert::ok($sod);
    expect(collect($sod->json('data'))->pluck('code'))->toContain('SOD-01');

    CatalogAssert::ok($this->getJson('/api/v1/platform/iam/permissions/ad.iam.view_catalog/holders'), ['roles', 'users']);
    CatalogAssert::ok($this->getJson('/api/v1/platform/iam/approval-requests?filter[status]=pending'));
});

it('creates a draft role and rejects approval by the creator', function () {
    $creator = platformAdmin();

    $created = $this->postJson('/api/v1/platform/iam/roles', [
        'name' => 'مشرف كتالوج',
        'key' => 'catalog_supervisor',
        'system' => 'channel',
        'description' => 'صلاحيات الكتالوج دون المالية',
        'permissions' => ['sc.catalog.view', 'sc.catalog.create'],
    ]);
    CatalogAssert::ok($created, ['id'], 201);
    expect($created->json('data.status'))->toBe('draft');

    $id = (int) $created->json('data.id');

    CatalogAssert::error(
        $this->postJson("/api/v1/platform/iam/roles/{$id}/approve", ['reason' => 'مراجعة SOD مكتملة']),
        403,
        'sod_violation',
    );

    $approver = PlatformUser::factory()->create();
    $approver->assignRole('platform_admin');
    Sanctum::actingAs($approver, ['*'], 'platform');

    CatalogAssert::ok(
        $this->postJson("/api/v1/platform/iam/roles/{$id}/approve", ['reason' => 'مراجعة SOD مكتملة']),
        ['status'],
    );

    expect(AccessRole::query()->find($id)?->status->value)->toBe('active');
    expect($creator->id)->not->toBe($approver->id);
})->group('permissions');

it('rejects approving a role that violates SOD-01', function () {
    platformAdmin();

    $created = $this->postJson('/api/v1/platform/iam/roles', [
        'name' => 'دور مخالف',
        'key' => 'sod_breaker',
        'system' => 'channel',
        'permissions' => ['sc.orders.confirm', 'sc.finance.payment'],
    ]);
    CatalogAssert::ok($created, [], 201);
    expect($created->json('data.sod_conflicts'))->not->toBeEmpty();

    $id = (int) $created->json('data.id');
    $approver = PlatformUser::factory()->create();
    $approver->assignRole('platform_admin');
    Sanctum::actingAs($approver, ['*'], 'platform');

    CatalogAssert::error(
        $this->postJson("/api/v1/platform/iam/roles/{$id}/approve", ['reason' => 'يجب أن يُرفض']),
        403,
        'sod_violation',
    );
})->group('permissions');

it('assigns a role and records the audit export', function () {
    platformAdmin();
    $channelUser = ChannelUser::factory()->create();
    $roleId = AccessRole::query()->where('name', 'channel_manager')->value('id');

    $assigned = $this->postJson('/api/v1/platform/iam/assignments', [
        'user_ids' => [$channelUser->id],
        'role_id' => $roleId,
        'reason' => 'تعيين مدير قناة جديد',
    ]);
    CatalogAssert::ok($assigned);
    expect($assigned->json('data.assigned'))->toContain($channelUser->id);

    $simulate = $this->postJson('/api/v1/platform/iam/simulate', [
        'user_type' => 'channel',
        'user_id' => $channelUser->id,
        'permission' => 'sc.orders.confirm',
    ]);
    CatalogAssert::ok($simulate, ['allowed', 'decision_path']);
    expect($simulate->json('data.decision_path'))->toHaveCount(5);

    $export = $this->postJson('/api/v1/platform/audit/export', [
        'filters' => ['date_from' => '2026-01-01', 'date_to' => '2026-12-31'],
        'format' => 'xlsx',
    ]);
    CatalogAssert::ok($export, ['job_id']);
    expect(AuditLog::query()->where('action', 'audit.export')->exists())->toBeTrue();

    CatalogAssert::ok($this->getJson('/api/v1/platform/audit'));
})->group('permissions');

it('rejects an assignment that would combine SOD-01 on the same user', function () {
    platformAdmin();
    $user = ChannelUser::factory()->create();
    $user->assignRole('accountant');

    $created = $this->postJson('/api/v1/platform/iam/roles', [
        'name' => 'مؤكد طلبات',
        'key' => 'order_confirmer',
        'system' => 'channel',
        'permissions' => ['sc.orders.confirm'],
    ]);
    $id = (int) $created->json('data.id');

    $approver = PlatformUser::factory()->create();
    $approver->assignRole('platform_admin');
    Sanctum::actingAs($approver, ['*'], 'platform');

    $this->postJson("/api/v1/platform/iam/roles/{$id}/approve", ['reason' => 'دور آمن']);

    $result = $this->postJson('/api/v1/platform/iam/assignments', [
        'user_ids' => [$user->id],
        'role_id' => $id,
        'reason' => 'محاولة جمع تأكيد ودفع',
    ]);
    CatalogAssert::ok($result);
    expect($result->json('data.assigned'))->toBeEmpty()
        ->and($result->json('data.rejected.0.user_id'))->toBe($user->id);
})->group('permissions');

it('previews a role, starts a review, and decides dual-approval via the inbox', function () {
    $creator = platformAdmin();

    $preview = $this->postJson('/api/v1/platform/iam/roles/preview', [
        'permissions' => ['sc.orders.confirm', 'sc.pricing.update'],
    ]);
    CatalogAssert::ok($preview, ['summary_ar', 'sod_conflicts']);

    $created = $this->postJson('/api/v1/platform/iam/roles', [
        'name' => 'دور صندوق',
        'key' => 'inbox_role',
        'system' => 'channel',
        'permissions' => ['sc.catalog.view'],
    ]);
    $roleId = (int) $created->json('data.id');

    $inbox = $this->getJson('/api/v1/platform/iam/approval-requests?filter[status]=pending');
    CatalogAssert::ok($inbox);
    $requestId = collect($inbox->json('data'))->firstWhere('action', 'role.create')['id'] ?? null;
    expect($requestId)->not->toBeNull();

    CatalogAssert::error(
        $this->postJson("/api/v1/platform/iam/approval-requests/{$requestId}/decide", [
            'decision' => 'approve',
            'reason' => 'لا يجوز للمنشئ',
        ]),
        403,
        'sod_violation',
    );

    $approver = PlatformUser::factory()->create();
    $approver->assignRole('platform_admin');
    Sanctum::actingAs($approver, ['*'], 'platform');

    CatalogAssert::ok($this->postJson("/api/v1/platform/iam/approval-requests/{$requestId}/decide", [
        'decision' => 'approve',
        'reason' => 'مراجعة مكتملة',
    ]), ['status', 'executed']);

    $review = $this->postJson('/api/v1/platform/iam/reviews', [
        'quarter' => '2026-Q1',
        'scope' => 'platform',
    ]);
    CatalogAssert::ok($review, ['campaign_id', 'items_count']);
    $campaignId = (int) $review->json('data.campaign_id');

    $detail = $this->getJson("/api/v1/platform/iam/reviews/{$campaignId}");
    CatalogAssert::ok($detail, ['items']);
    $itemId = (int) $detail->json('data.items.0.id');

    CatalogAssert::ok($this->postJson("/api/v1/platform/iam/reviews/{$campaignId}/items/{$itemId}/decide", [
        'decision' => 'keep',
        'reason' => 'ما زال مدير قناة فعّال',
    ]), ['item_id', 'decision']);

    $channelUser = ChannelUser::factory()->create();
    $grant = $this->postJson('/api/v1/platform/iam/temp-grants', [
        'user_id' => $channelUser->id,
        'permission' => 'sc.finance.void_invoice',
        'duration_minutes' => 60,
        'reason' => 'تصحيح فاتورة مكررة بعد حادث مزامنة — تذكرة SUP-441',
    ]);
    CatalogAssert::ok($grant, ['id', 'status']);
    expect($grant->json('data.status'))->toBe('pending_approval');
    expect($creator->id)->toBeInt();
    expect($roleId)->toBeInt();
});

it('gates replace-permissions on ad.iam.role_update, not role_create', function () {
    $this->seed(RolesPermissionsSeeder::class);

    $holder = PlatformUser::factory()->create();
    $holder->givePermissionTo('ad.iam.role_create');

    $roleId = AccessRole::query()->where('name', 'channel_manager')->value('id');

    $this->putJson("/api/v1/platform/iam/roles/{$roleId}/permissions", [
        'permissions' => ['sc.catalog.view'],
        'reason' => 'محاولة تعديل بصلاحية الإنشاء فقط',
    ], [
        'Authorization' => 'Bearer '.$holder->createToken('create-only', ['*'])->plainTextToken,
    ])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'insufficient_permission')
        ->assertJsonPath('error.permission', 'ad.iam.role_update');
})->group('permissions');

it('exposes every catalog code through SP-17', function () {
    // `ad.billing.manage` was asserted here and has been removed: it was in neither
    // DOC-08 nor the API catalog, and this assertion was one of the two things keeping
    // it alive. `ad.billing.assign_plan` is the code the catalog actually gates
    // EP-AD-055 on, so it is the one worth pinning.
    expect(count(PermissionCatalog::codes()))->toBeGreaterThanOrEqual(127)
        ->and(PermissionCatalog::exists('ad.iam.view_catalog'))->toBeTrue()
        ->and(PermissionCatalog::exists('ad.billing.assign_plan'))->toBeTrue()
        ->and(PermissionCatalog::exists('ad.billing.manage'))->toBeFalse();
});
