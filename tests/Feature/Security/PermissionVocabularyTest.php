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
 * AccessMatrix is gone. One vocabulary remains and the assertions below hold, with two
 * named exemptions rather than a loosened rule:
 *
 *  - `sc.notify.view` — real. `EP-SC-092 GET /channel/notifications/log` carries it in
 *    the API catalog, the OpenAPI document and the Postman collection. DOC-08's text is
 *    what is behind here, not the code.
 *  - `ad.billing.manage` — a phantom with no route and no catalog entry. The sprint spec
 *    assigns it to EP-AD-055, which the catalog itself gates on `ad.billing.assign_plan`.
 *    It survives because `bin/extract-permissions.php` re-injects it on every generation
 *    and `IamTest` asserts it exists. Resolving that contradiction belongs to BE-T12.
 *
 * Naming them here is deliberate: a third stray code must fail this file, so the rule
 * stays "nothing outside DOC-08" plus a written exemption, not "mostly DOC-08".
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
 * The two codes this file permits outside DOC-08, each for a reason written in the
 * docblock above. Not a wildcard and not a count — the exact names, so a third one fails.
 */
const DOC08_EXEMPT = ['ad.billing.manage', 'sc.notify.view'];

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

    // WAS, before the vocabulary batch: five lines, 332 rows — the whole 66-name
    // AccessMatrix vocabulary on each of `app`, `channel`, `platform`, `warehouse` and
    // `web`, plus the two exemptions.
    // IS: one row per exemption and nothing else. Every other seeded name is verbatim
    // from DOC-08, on the single guard its `system` names.
    expect($report)->toBe([
        'channel: 1 rows — sc.notify.view',
        'platform: 1 rows — ad.billing.manage',
    ]);
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

    // WAS: six of the eight roles, each carrying the full 66-name interim vocabulary or
    // a slice of it — channel_manager held 67 names of 110 that DOC-08 never defined.
    // IS: only the two roles that receive a whole system's codes, and only because each
    // system contains one exemption. The six other roles are clean.
    expect($report)->toBe([
        'platform_admin (platform): 1 of 62 — ad.billing.manage',
        'channel_manager (channel): 1 of 48 — sc.notify.view',
    ]);
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
    // not define, spread across every role but the two app ones.
    // IS: one, the phantom. No channel, warehouse or app role can write through a name
    // outside the document any more.
    expect($report)->toBe([
        'platform_admin (platform): 1 — ad.billing.manage',
    ]);
})->group('security');

it('pins the shape of the catalog so the gap cannot widen unnoticed', function () {
    // Written when the assertions above were red — a red rule cannot signal the next
    // breach of the same rule, so this one counted instead. They are green now and it
    // still earns its place: it is the assertion that moves when a DOC-08 code is added
    // to the catalog, which is how the remaining 39 are meant to arrive, one route at a
    // time. `sc.settings.*` and `sc.zones.*` arrived exactly that way in this batch.
    $catalog = doc08Codes();

    expect(Permission::query()->count())->toBe(133)
        ->and(PermissionCatalog::codes())->toHaveCount(133)
        ->and(count(array_diff($catalog, PermissionCatalog::codes())))->toBe(39)
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

    $response = $this->postJson('/api/v1/governorates', [
        'name_ar' => 'درعا',
        'name_en' => 'Daraa',
        'code' => 'DRA',
    ], ['Authorization' => 'Bearer '.$token]);

    // The holder is authenticated — this is 403, not 401, and not `wrong_guard`: the
    // channel guard is one of the four this route accepts. It is the permission that
    // fails, which is exactly the distinction the error code carries.
    expect($response->getStatusCode())->toBe(403)
        ->and($response->json('error.code'))->toBe('insufficient_permission')
        ->and(Governorate::where('code', 'DRA')->exists())->toBeFalse();
})->group('security');
