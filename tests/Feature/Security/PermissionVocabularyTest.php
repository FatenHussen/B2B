<?php

declare(strict_types=1);

/**
 * The permission vocabulary, measured against DOC-08.
 *
 * CLAUDE.md, Naming: "Permissions — from the DOC-08 catalog, verbatim".
 *
 * This file was written to measure the distance from that rule while two vocabularies
 * were seeded side by side: `PermissionCatalog`, the code's copy of DOC-08, and
 * `AccessMatrix`, 66 bare `{unit}.{action}` names seeded on all four guards plus `web`.
 * 129 + (66 x 5) = 459 rows, and the interim names were live: every write route under
 * `Modules\Reference` and `Modules\Tenancy` ran on `can:settings.*`, which existed only
 * in AccessMatrix and on every guard at once, so a channel manager could create a
 * governorate. The last test here is that probe, now inverted.
 *
 * AccessMatrix is gone. Every assertion below is an empty list with one named exception:
 * nothing is seeded, held by a role, or writable through a name DOC-08 does not define,
 * other than the single catalog-only code in DOC08_EXEMPT.
 *
 * Two codes were once exempt here. `ad.billing.manage` was in neither DOC-08 nor the API
 * catalog and survived only because `bin/extract-permissions.php` re-injected it on every
 * generation, so the generator was fixed and the code is gone. `sc.notify.view` stays: it
 * guards EP-SC-092 `GET /channel/notifications/log`, a route the catalog defines and the
 * Notification module publishes, which DOC-08 has not caught up with. The catalog is the
 * source of the path, so the code is seeded and exempted with that endpoint named beside
 * it — not deleted, and not given an invented DOC-08 entry.
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
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Reference\Domain\Models\Governorate;
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
 * Codes permitted outside DOC-08: the ones the API catalog defines and DOC-08 does not.
 * One today. An entry here carries the endpoint it serves, and nothing enters without one.
 *
 * `ad.billing.manage` was once listed too. It was a phantom — in neither DOC-08 nor the
 * API catalog, kept alive only because `bin/extract-permissions.php` re-injected it on
 * every generation; the generator was fixed rather than the exemption maintained.
 * `sc.notify.view` guards EP-SC-092 `GET /channel/notifications/log`. It is not in DOC-08;
 * the catalog is the source of the path, so the code is seeded with that endpoint and
 * listed here.
 */
const DOC08_EXEMPT = ['sc.notify.view'];

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
        if (in_array($permission->name, DOC08_EXEMPT, true)) {
            continue;
        }
        if (! in_array($permission->name, $catalog, true)) {
            $offenders[$permission->guard_name][] = $permission->name;
        }
    }

    $report = [];
    foreach ($offenders as $guard => $names) {
        $report[] = sprintf('%s: %d rows — %s', $guard, count($names), implode(' ', $names));
    }

    // WAS, before the vocabulary batch: five lines, 332 rows — the whole 66-name
    // AccessMatrix vocabulary on each of `app`, `channel`, `platform`, `warehouse` and
    // `web`. Then two lines, one per exemption.
    // IS: nothing. Every seeded name is verbatim from DOC-08, on the single guard its
    // `system` names.
    expect($report)->toBe([]);
})->group('security');

it('gives no role a permission that DOC-08 does not define', function () {
    $catalog = doc08Codes();

    $report = [];
    foreach (ROLE_GUARDS as $name => $guard) {
        $role = Role::query()->where('name', $name)->where('guard_name', $guard)->firstOrFail();
        $held = $role->permissions->pluck('name')->all();
        sort($held);
        $extra = array_values(array_diff($held, $catalog, DOC08_EXEMPT));

        if ($extra !== []) {
            $report[] = sprintf('%s (%s): %d of %d — %s', $name, $guard, count($extra), count($held), implode(' ', $extra));
        }
    }

    // WAS: six of the eight roles, each carrying the full 66-name interim vocabulary or
    // a slice of it — channel_manager held 67 names of 110 that DOC-08 never defined.
    // IS: nothing. No role holds a name the document does not define.
    expect($report)->toBe([]);
})->group('security');

it('names the roles that can write through a permission DOC-08 does not define', function () {
    $catalog = doc08Codes();

    // The action segment of a name that changes state. A role holding a stray `*.view`
    // is noise; a role holding a stray write verb is an authorisation hole. `manage` is
    // in the list on purpose, so the surviving `ad.billing.manage` is counted honestly
    // rather than filtered out by a verb list that happens to omit it.
    $writes = ['create', 'update', 'delete', 'approve', 'manage', 'fulfil', 'handover', 'wallets', 'financial', 'receive'];

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

    // WAS: platform_admin 54, channel_manager 54, sales_manager 14, catalog_manager 10,
    // accountant 7, warehouse_keeper 7 — 146 grants to write through a name DOC-08 does
    // not define. Then one, the phantom.
    // IS: none.
    expect($report)->toBe([]);
})->group('security');

it('pins the shape of the catalog so the gap cannot widen unnoticed', function () {
    // Written when the assertions above were red — a red rule cannot signal the next
    // breach of the same rule, so this one counted instead. They are green now and it
    // still earns its place: it is the assertion that moves when a DOC-08 code is added
    // to the catalog, which is how the remaining 37 are meant to arrive, one route at a
    // time. `sc.settings.*` and `sc.zones.*` arrived exactly that way, and so did the
    // three `sc.reps.*` codes below.
    $catalog = doc08Codes();

    // 141, up from 140: `wh.reports.view` (EP-WH-050).
    expect(Permission::query()->count())->toBe(141)
        ->and(PermissionCatalog::codes())->toHaveCount(141)
        // 30, down from 31: wh.reports.view found its route.
        ->and(count(array_diff($catalog, PermissionCatalog::codes())))->toBe(30)
        ->and(array_values(array_diff(PermissionCatalog::codes(), $catalog)))
        ->toBe(DOC08_EXEMPT);
})->group('security');

it('seeds each catalog code exactly once, on the guard its system names', function () {
    // The `web` guard is gone with AccessMatrix — it held 66 rows that no role carried
    // and no route consulted. Nothing should be seeded on more than one guard now: the
    // cross-guard reach of a single name is what made `settings.create` exploitable.
    $duplicated = Permission::query()
        ->selectRaw('name, count(*) as guards')
        ->groupBy('name')
        ->havingRaw('count(*) > 1')
        ->pluck('name')
        ->all();

    expect($duplicated)->toBe([])
        ->and(Permission::query()->where('guard_name', 'web')->count())->toBe(0);
})->group('security');

it('refuses a channel manager creating a governorate', function () {
    // The former exploit path, end to end, with a real token. `POST /api/v1/governorates`
    // is published by Modules\Reference under `auth:platform,channel,warehouse,app` —
    // four guards, one group. It was gated on `can:settings.create`, an AccessMatrix name
    // seeded on all five guards, so the channel guard satisfied a gate written for the
    // platform and this test recorded 201 with the row written. The gate now names
    // `ad.refs.create`, which exists on the platform guard alone.
    //
    // The group is deliberately still four guards. Splitting it would hide what is being
    // measured: that the gate itself, not the route grouping, is what refuses the caller.
    $channel = SupplyChannel::factory()->create();
    $manager = ChannelUser::factory()->forChannel($channel)->create();
    $manager->assignRole('channel_manager');

    // Not `hasPermissionTo`, which throws PermissionDoesNotExist for a code absent from
    // this guard. `can()` goes through the gate — the same path the middleware takes —
    // and that swallowed exception is why the answer is 403 and not a 500.
    expect($manager->can('ad.refs.create'))->toBeFalse();

    $token = $manager->createToken('vocabulary-probe', ['*'])->plainTextToken;

    $response = $this->postJson('/api/v1/platform/refs/governorates', [
        'name_ar' => 'درعا',
        'name_en' => 'Daraa',
        'code' => 'DRA',
    ], ['Authorization' => 'Bearer '.$token]);

    // Reference writes moved under the platform prefix (BE-R01), so a channel token now
    // fails the guard before it reaches the gate: 403 `wrong_guard`. Either way the row
    // is not written, which is what this test has always measured.
    expect($response->getStatusCode())->toBe(403)
        ->and($response->json('error.code'))->toBe('wrong_guard')
        ->and(Governorate::where('code', 'DRA')->exists())->toBeFalse();
})->group('security');
