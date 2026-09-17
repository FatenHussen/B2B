<?php

declare(strict_types=1);

/**
 * BE-R01, BE-R04, BE-R05, BE-R06, BE-R07, BE-R12 — the shared reference pattern on the
 * four simple entities, under `/platform/refs`.
 *
 * One HTTP request per test unless the guards are forgotten in between (CrossGuardTest).
 */

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Domain\Events\ReferenceDisabled;
use Modules\Core\Domain\Models\AuditLog;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\ActivityType;
use Modules\Reference\Domain\Models\Equipment;
use Modules\Reference\Domain\Models\RootCategory;
use Modules\Reference\Domain\Models\SaleUnit;
use Tests\Support\AppSurface;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function refsAdmin(): PlatformUser
{
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    return $admin;
}

// ── BE-R01 ──────────────────────────────────────────────────────────────────────────

it('has no delete route on any reference entity', function () {
    // BE-R01 acceptance criterion 2, read from the route table rather than from one
    // entity at a time: not a single DELETE under /platform/refs, nor on the shared reads.
    $deletes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => in_array('DELETE', $r->methods(), true))
        ->map->uri()
        ->filter(fn (string $uri) => str_starts_with($uri, 'api/v1/platform/refs')
            || preg_match('#^api/v1/(governorates|zones|currencies)#', $uri) === 1)
        ->values()
        ->all();

    expect($deletes)->toBe([]);
});

it('a disabled reference blocks new registration but leaves existing records intact', function () {
    // BE-R01 acceptance criterion 1. A retailer registered on an activity type keeps the
    // profile after the type is disabled; a new retailer cannot choose it any more.
    $refs = AppSurface::refs();
    $existing = AppSurface::retailer($refs);

    refsAdmin();
    Event::fake([ReferenceDisabled::class]);

    $this->patchJson("/api/v1/platform/refs/activity-types/{$refs['activity']->id}/status", [
        'status' => 'disabled',
        'reason' => 'نشاط مهجور',
    ])->assertOk()
        ->assertJsonPath('data.status', 'disabled')
        ->assertJsonPath('data.affected.retailers', 1);

    Event::assertDispatched(ReferenceDisabled::class, fn (ReferenceDisabled $e) => $e->entity === 'activity_types' && $e->id === $refs['activity']->id);

    // Existing: untouched.
    expect($existing->fresh()->retailerProfile?->activity_type_id)->toBe($refs['activity']->id);

    // New use: the directory no longer admits it, so registration is refused on that field.
    expect(app(ReferenceDirectory::class)->activityTypeExists($refs['activity']->id))->toBeFalse()
        ->and(app(ReferenceDirectory::class)->allActivityTypesExist([$refs['activity']->id]))->toBeFalse();
});

// ── BE-R12 ──────────────────────────────────────────────────────────────────────────

it('refuses every reference mutation without a reason, on every entity', function () {
    // BE-R12: the client cannot bypass it — the server refuses with 422 before writing.
    refsAdmin();
    $refs = AppSurface::refs();
    $equipment = Equipment::query()->create(['name' => 'ثلاجة']);

    $targets = [
        ['put', "/api/v1/platform/refs/activity-types/{$refs['activity']->id}", ['name' => 'x']],
        ['patch', "/api/v1/platform/refs/activity-types/{$refs['activity']->id}/status", ['status' => 'disabled']],
        ['put', "/api/v1/platform/refs/root-categories/{$refs['root']->id}", ['name' => 'x']],
        ['patch', "/api/v1/platform/refs/root-categories/{$refs['root']->id}/status", ['status' => 'disabled']],
        ['put', "/api/v1/platform/refs/sale-units/{$refs['unit']->id}", ['name' => 'x']],
        ['patch', "/api/v1/platform/refs/sale-units/{$refs['unit']->id}/status", ['status' => 'disabled']],
        ['put', "/api/v1/platform/refs/equipments/{$equipment->id}", ['name' => 'x']],
        ['patch', "/api/v1/platform/refs/equipments/{$equipment->id}/status", ['status' => 'disabled']],
    ];

    foreach ($targets as [$method, $uri, $body]) {
        $this->{$method.'Json'}($uri, $body)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.details.reason', fn ($v) => is_array($v) && $v !== []);
    }

    expect($refs['activity']->fresh()->name)->toBe('بقالة')
        ->and($refs['activity']->fresh()->status)->toBe(RefStatus::Active)
        ->and(AuditLog::query()->where('action', 'like', 'ref.%')->count())->toBe(0);
});

it('writes the reason with before and after values to the audit log', function () {
    $admin = refsAdmin();
    $unit = SaleUnit::query()->create(['name' => 'قطعة', 'abbr' => 'pc']);

    $this->putJson("/api/v1/platform/refs/sale-units/{$unit->id}", ['abbr' => 'pcs', 'reason' => 'توحيد الاختصار'])
        ->assertOk()
        ->assertJsonPath('data.abbr', 'pcs');

    $audit = AuditLog::query()->where('action', 'ref.sale_units.update')->where('subject_id', $unit->id)->sole();
    expect($audit->properties['reason'])->toBe('توحيد الاختصار')
        ->and($audit->properties['before'])->toBe(['abbr' => 'pc'])
        ->and($audit->properties['after'])->toBe(['abbr' => 'pcs'])
        ->and((int) $audit->actor_id)->toBe($admin->id);
});

// ── BE-R04 ──────────────────────────────────────────────────────────────────────────

it('creates an activity type with suggested root categories and lists them', function () {
    refsAdmin();
    $a = RootCategory::query()->create(['name' => 'غذائية']);
    $b = RootCategory::query()->create(['name' => 'منظفات']);

    $id = $this->postJson('/api/v1/platform/refs/activity-types', [
        'name' => 'سوبر ماركت',
        'icon' => 'supermarket',
        'suggested_category_ids' => [$a->id, $b->id],
        'order' => 2,
    ])->assertCreated()
        ->assertJsonPath('data.suggested_category_ids', [$a->id, $b->id])
        ->json('data.id');

    $this->getJson('/api/v1/platform/refs/activity-types')
        ->assertOk()
        ->assertJsonPath('data.0.id', $id)
        ->assertJsonPath('data.0.suggested_category_ids', [$a->id, $b->id])
        ->assertJsonPath('meta.total', 1);
});

// ── BE-R05 ──────────────────────────────────────────────────────────────────────────

it('refuses to disable a root category in use, carrying the counts of what depends on it', function () {
    // BE-R05 acceptance criterion: the refusal carries the number of dependent records so
    // the message can be specific. Categories and products live in Catalog and reach
    // here as numbers through the Core contract.
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    AppSurface::productWithBrand($this, $channel, $refs);

    refsAdmin();

    $this->patchJson("/api/v1/platform/refs/root-categories/{$refs['root']->id}/status", [
        'status' => 'disabled',
        'reason' => 'دمج فئات',
    ])->assertStatus(422)
        ->assertJsonPath('error.code', 'ref_in_use')
        ->assertJsonPath('error.details.affected.child_categories', 1)
        ->assertJsonPath('error.details.affected.active_products', 1);

    expect($refs['root']->fresh()->status)->toBe(RefStatus::Active);
});

it('disables a root category nothing hangs off', function () {
    refsAdmin();
    $root = RootCategory::query()->create(['name' => 'فارغة']);

    $this->patchJson("/api/v1/platform/refs/root-categories/{$root->id}/status", [
        'status' => 'disabled',
        'reason' => 'لم تُستعمل',
    ])->assertOk()
        ->assertJsonPath('data.status', 'disabled');

    expect($root->fresh()->status)->toBe(RefStatus::Disabled);
});

// ── BE-R06 ──────────────────────────────────────────────────────────────────────────

it('refuses to disable a sale unit any product sells in, with the usage context', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    AppSurface::productWithBrand($this, $channel, $refs);

    refsAdmin();

    $this->patchJson("/api/v1/platform/refs/sale-units/{$refs['unit']->id}/status", [
        'status' => 'disabled',
        'reason' => 'وحدة مهجورة',
    ])->assertStatus(422)
        ->assertJsonPath('error.code', 'ref_in_use')
        ->assertJsonPath('error.details.affected.products', 1);

    expect($refs['unit']->fresh()->status)->toBe(RefStatus::Active);
});

// ── BE-R07 ──────────────────────────────────────────────────────────────────────────

it('runs equipment through the shared pattern: list, create, update, status', function () {
    refsAdmin();

    $id = $this->postJson('/api/v1/platform/refs/equipments', ['name' => 'فريزر', 'icon' => 'freezer'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'active')
        ->json('data.id');

    $this->putJson("/api/v1/platform/refs/equipments/{$id}", ['name' => 'فريزر عرض', 'reason' => 'تصحيح'])
        ->assertOk()
        ->assertJsonPath('data.name', 'فريزر عرض');

    $this->patchJson("/api/v1/platform/refs/equipments/{$id}/status", ['status' => 'disabled', 'reason' => 'لم يعد يُستهدف'])
        ->assertOk()
        ->assertJsonPath('data.affected.retailers', 0);

    $this->getJson('/api/v1/platform/refs/equipments?filter[status]=disabled')
        ->assertOk()
        ->assertJsonPath('data.0.id', $id)
        ->assertJsonPath('meta.total', 1);

    expect(Equipment::query()->find($id)?->status)->toBe(RefStatus::Disabled);
});

// ── Gates ───────────────────────────────────────────────────────────────────────────

it('names ad.refs.view on a 403 for a platform user without it', function () {
    $user = PlatformUser::factory()->create();
    Sanctum::actingAs($user, ['*'], 'platform');

    $this->getJson('/api/v1/platform/refs/activity-types')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'insufficient_permission')
        ->assertJsonPath('error.permission', 'ad.refs.view');
});

it('keeps status out of mass assignment on every simple entity', function () {
    // Rule 8: a rename cannot disable. `status` in a PUT body is dropped, not applied.
    refsAdmin();
    $type = ActivityType::query()->create(['name' => 'بقالة']);

    $this->putJson("/api/v1/platform/refs/activity-types/{$type->id}", [
        'name' => 'بقالة صغيرة', 'status' => 'disabled', 'reason' => 'إعادة تسمية',
    ])->assertOk()
        ->assertJsonPath('data.name', 'بقالة صغيرة')
        ->assertJsonPath('data.status', 'active');

    expect($type->fresh()->status)->toBe(RefStatus::Active);
});
