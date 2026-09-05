<?php

declare(strict_types=1);

/**
 * The permission vocabulary, measured against DOC-08.
 *
 * CLAUDE.md, Naming: "Permissions — from the DOC-08 catalog, verbatim". This file does
 * not enforce that rule, it measures the distance from it. Every assertion below either
 * fails with the exact list of offending names, or pins today's numbers so the gap
 * cannot widen unnoticed while the failing assertions stay red.
 *
 * Two vocabularies are seeded side by side:
 *
 *  1. `PermissionCatalog` — 129 `{system}.{module}.{action}` codes, the code's copy of
 *     DOC-08, seeded once each on the guard its `system` names.
 *  2. `AccessMatrix` — 66 bare `{unit}.{action}` names carrying a docblock that calls
 *     itself "interim ... until DOC-08's 170 SystemPermission values land in SP-02",
 *     seeded on all five guards including `web`, which no role and no route uses.
 *
 * 129 + (66 x 5) = 459 rows. The second vocabulary is live, not vestigial: every write
 * route under `Modules\Reference` and `Modules\Tenancy` is gated on `can:settings.*`,
 * which exists only in AccessMatrix. That is what the last test here exercises.
 *
 * Hazards this file is shaped around, inherited from CrossGuardTest:
 *
 *  1. `Sanctum::actingAs()` never exercises token resolution, so it cannot measure what
 *     a guard does with a real credential. The route probe uses a real bearer token.
 *  2. A guard caches its resolved user for the lifetime of the application, so a second
 *     request in the same test reuses the first token's holder. One request per test.
 */

use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Access\Domain\PermissionCatalog;
use Modules\Access\Domain\Support\AccessMatrix;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

/** The eight builtin roles and the guard each one is seeded on. */
const ROLE_GUARDS = [
    'platform_admin' => 'platform',
    'channel_manager' => 'channel',
    'sales_manager' => 'channel',
    'catalog_manager' => 'channel',
    'accountant' => 'channel',
    'warehouse_keeper' => 'warehouse',
    'retailer' => 'app',
    'rep' => 'app',
];

/**
 * The DOC-08 catalog as the document defines it, read from the document rather than
 * from the code's copy of it. `docs/api/doc08.txt` is the extracted text of
 * DOC-08 / PERM-FSD v1.0, whose own header states it defines 170 permissions across
 * five systems; the regex finds exactly 170 distinct codes, so the extraction is whole.
 *
 * @return list<string>
 */
function doc08Codes(): array
{
    $text = (string) file_get_contents(base_path('docs/api/doc08.txt'));
    preg_match_all('/\b(?:ad|sc|wh|rp|rt)\.[a-z_]+\.[a-z_]+\b/u', $text, $matches);

    $codes = array_values(array_unique($matches[0]));
    sort($codes);

    return $codes;
}

it('reads 170 permission codes out of DOC-08 itself', function () {
    // The guard on every other test in this file. If the extraction ever stops finding
    // the number DOC-08 claims in its own header, the diffs below measure noise.
    expect(doc08Codes())->toHaveCount(170);
})->group('security');

it('seeds no permission name that DOC-08 does not define', function () {
    $catalog = doc08Codes();

    $offenders = [];
    foreach (Permission::query()->orderBy('guard_name')->orderBy('name')->get() as $permission) {
        if (! in_array($permission->name, $catalog, true)) {
            $offenders[$permission->guard_name][] = $permission->name;
        }
    }

    $report = [];
    foreach ($offenders as $guard => $names) {
        $report[] = sprintf('%s: %d rows — %s', $guard, count($names), implode(' ', $names));
    }

    // SHOULD BE per CLAUDE.md: no line at all.
    // IS, recorded 2026-09-05: five lines, 332 rows — the whole 66-name AccessMatrix
    // vocabulary on each of `app`, `channel`, `platform`, `warehouse` and `web`, plus
    // the two PermissionCatalog codes DOC-08 never defines: `sc.notify.view` on the
    // channel guard and `ad.billing.manage` on the platform guard.
    expect($report)->toBe([]);
})->group('security');

it('gives no role a permission that DOC-08 does not define', function () {
    $catalog = doc08Codes();

    $report = [];
    foreach (ROLE_GUARDS as $name => $guard) {
        $role = Role::query()->where('name', $name)->where('guard_name', $guard)->firstOrFail();
        $held = $role->permissions->pluck('name')->all();
        sort($held);
        $extra = array_values(array_diff($held, $catalog));

        if ($extra !== []) {
            $report[] = sprintf('%s (%s): %d of %d — %s', $name, $guard, count($extra), count($held), implode(' ', $extra));
        }
    }

    // SHOULD BE per CLAUDE.md: no line at all.
    // IS, recorded 2026-09-05: six of the eight roles. `retailer` and `rep` are clean
    // because PermissionCatalog is their only source; AccessMatrix defines no role for
    // either. Every other role carries the full 66-name interim vocabulary or a slice.
    expect($report)->toBe([]);
})->group('security');

it('names the roles that can write through a permission DOC-08 does not define', function () {
    $catalog = doc08Codes();

    // The action segment of a name that changes state. `view` is the only read verb in
    // AccessMatrix; a role holding only `*.view` outside the catalog is noise, a role
    // holding `settings.create` outside it is an authorisation hole.
    $writes = ['create', 'update', 'delete', 'approve', 'fulfil', 'handover', 'wallets', 'financial', 'receive'];

    $report = [];
    foreach (ROLE_GUARDS as $name => $guard) {
        $role = Role::query()->where('name', $name)->where('guard_name', $guard)->firstOrFail();
        $extra = array_diff($role->permissions->pluck('name')->all(), $catalog);
        $writers = array_values(array_filter($extra, function (string $permission) use ($writes): bool {
            $segments = explode('.', $permission);

            return in_array(end($segments), $writes, true);
        }));
        sort($writers);

        if ($writers !== []) {
            $report[] = sprintf('%s (%s): %d — %s', $name, $guard, count($writers), implode(' ', $writers));
        }
    }

    // SHOULD BE per CLAUDE.md: no line at all.
    // IS, recorded 2026-09-05: platform_admin 54, channel_manager 54, sales_manager 14,
    // catalog_manager 10, accountant 7, warehouse_keeper 7.
    expect($report)->toBe([]);
})->group('security');

it('pins the size of the gap so it cannot widen while the assertions above stay red', function () {
    // A red rule cannot signal the next breach of the same rule — the assertions above
    // stay red until the vocabulary is replaced, and a 67th interim name added tomorrow
    // would change nothing about how they fail. This one is green and counts, so it
    // moves the day anything is added or removed on either side.
    $catalog = doc08Codes();

    expect(Permission::query()->count())->toBe(459)
        ->and(PermissionCatalog::codes())->toHaveCount(129)
        ->and(AccessMatrix::permissions())->toHaveCount(66)
        ->and(count(array_diff($catalog, PermissionCatalog::codes())))->toBe(43)
        ->and(array_values(array_diff(PermissionCatalog::codes(), $catalog)))
        ->toBe(['ad.billing.manage', 'sc.notify.view']);
})->group('security');

it('lets a channel manager create a governorate with a permission DOC-08 does not define', function () {
    // The exploit path, end to end, with a real token. `POST /api/v1/governorates` is
    // published by Modules\Reference under `auth:platform,channel,warehouse,app` — four
    // guards, one group — and gated only by `can:settings.create`. `settings.create` is
    // an AccessMatrix name seeded on all five guards, and channel_manager holds the
    // whole AccessMatrix vocabulary. So the channel guard satisfies a gate written for
    // the platform guard, and a tenant user writes platform-owned reference data.
    $channel = SupplyChannel::factory()->create();
    $manager = ChannelUser::factory()->forChannel($channel)->create();
    $manager->assignRole('channel_manager');

    expect($manager->can('settings.create'))->toBeTrue();

    $token = $manager->createToken('vocabulary-probe', ['*'])->plainTextToken;

    $response = $this->postJson('/api/v1/governorates', [
        'name_ar' => 'درعا',
        'name_en' => 'Daraa',
        'code' => 'DRA',
    ], ['Authorization' => 'Bearer '.$token]);

    // SHOULD BE: 403 insufficient_permission. Creating a governorate is `ad.refs.create`
    // in DOC-08 — a platform code, which no channel role may hold.
    // IS, recorded 2026-09-05: 201. The row is written.
    expect($response->getStatusCode())->toBe(201);
})->group('security');
