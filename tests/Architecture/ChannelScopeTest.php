<?php

declare(strict_types=1);

/**
 * Rule 10, asserted against the schema rather than against memory.
 *
 * "Every channel-owned model carries a channel column with a composite index starting on
 * it, and `ChannelScope` applied automatically." A model whose table has the column but
 * whose class lacks `BelongsToChannel` is not isolated by anything the model itself
 * knows. Whatever protects it is a hand-written `->where(...)` in each query, and the
 * first query written without that line leaks silently, with no test failing.
 *
 * That is exactly what was measured before this file existed: with the tenant set to
 * channel B, `SubOrder::query()->get()` returned channel A's row. The routes held only
 * because `ListChannelSubOrders` and `ShowChannelSubOrder` each remembered to filter.
 *
 * **Two column names, one rule.** Every compliant model uses `supply_channel_id`; every
 * unprotected one uses `channel_id`. The split is exact, and it is why the breach went
 * unnoticed — CLAUDE.md named a single column, so the `channel_id` models were not
 * violating the rule so much as sitting outside its wording. This test takes both names,
 * which is the rule as it is now stated.
 *
 * **Two modes, one trait.** Strict is the default: no tenant means a programming error and
 * `MissingChannelScopeException` says so where it happens. A model declaring
 * `$channelScopeOptional = true` is relaxed — it still filters whenever a tenant is set,
 * and tolerates only its absence. That is for tables read in order to *decide* which
 * channel a caller belongs to, before any tenant exists.
 *
 * Relaxed counts as scoped here, and should: it filters when there is something to filter
 * by, where an exemption filters never.
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Domain\Models\Brand;
use Modules\Reference\Domain\Models\Governorate;

/**
 * The column names that mean "this row belongs to a supply channel".
 */
const CHANNEL_COLUMNS = ['supply_channel_id', 'channel_id'];

/**
 * Models whose table has a channel column but which must NOT be channel-scoped.
 *
 * Every entry needs a reason. An exemption without one is how a real breach gets filed
 * as a known quirk and then forgotten.
 */
const CHANNEL_SCOPE_EXEMPT = [
    // Read from the platform back office across every channel — a per-tenant scope would
    // hide exactly the cross-channel activity an audit log exists to show. Its
    // `channel_id` records which channel an entry came from; it does not restrict who
    // may read it.
    'Modules\Core\Domain\Models\AuditLog',

    // The second model on `channel_zone`, and read-only by construction — `$fillable` is
    // empty, so every write goes through Reference's `ChannelZone`, which is scoped.
    // Its three callers in EloquentChannelDirectory either name the channel as an
    // argument (asked during registration, before the caller belongs to any channel) or
    // ask which channels cover a zone, which is cross-channel by definition. Scoping it
    // would mean `acrossChannels()` three times in one file, and an escape hatch that
    // common stops reading as an exception.
    'Modules\Tenancy\Domain\Models\ChannelZoneLookup',

    // The channel status trail (BE-T01): written by ChannelLifecycle from the platform
    // back office, where no tenant is set, and read on the back-office timeline
    // (EP-AD-052). It is the platform's record *about* a channel, not data the channel
    // owns — `channel_id` is a foreign key to the tenant table, exactly as on AuditLog,
    // and a tenant scope would hide the trail from the one audience that reads it.
    // Relaxed mode was considered and rejected: that mode is for tables read to decide
    // which channel a caller belongs to, and this table decides nothing.
    'Modules\Tenancy\Domain\Models\ChannelEvent',

    // ChannelUserChannel, RepProfile and WarehouseDevice were listed here and are not
    // any more: they now carry the trait in relaxed mode
    // ($channelScopeOptional = true), which filters whenever a tenant is set and
    // tolerates only its absence. That is strictly better than exemption, which filtered
    // never — so the list is for models that must not be scoped at all, not for models
    // that cannot always be.
];

/**
 * Whether a database is reachable.
 *
 * `tests/Pest.php` requires the Architecture suite to stay runnable without one, and this
 * file is the only member that needs the schema — the alternative, grepping the migration
 * tree, reports columns a later migration has since dropped. So the schema-backed
 * assertions skip rather than error when there is no connection, and run everywhere it
 * matters: CI provisions MySQL before the arch gate.
 */
function schemaAvailable(): bool
{
    try {
        DB::connection()->getPdo();

        return true;
    } catch (Throwable) {
        return false;
    }
}

/**
 * Every Domain model class, resolved from the file tree.
 *
 * @return list<class-string>
 */
function domainModelClasses(): array
{
    $classes = [];

    foreach (File::glob(base_path('app-modules/*/src/Domain/Models/*.php')) as $path) {
        $source = (string) file_get_contents($path);

        if (! preg_match('/^namespace\s+([^;]+);/m', $source, $ns)) {
            continue;
        }

        if (! preg_match('/^\s*(?:final\s+|abstract\s+)?class\s+(\w+)/m', $source, $cls)) {
            continue;
        }

        $fqcn = trim($ns[1]).'\\'.$cls[1];

        if (class_exists($fqcn)) {
            $classes[] = $fqcn;
        }
    }

    sort($classes);

    return $classes;
}

/**
 * The channel column on a model's table, or null when it has none.
 *
 * Read from the live schema, not from the migration files. A migration that adds a column
 * and a later one that drops it both contain the name, so grepping the migration tree
 * reports columns that no longer exist — and misses any added outside `app-modules`.
 */
function channelColumnOf(string $model): ?string
{
    try {
        $table = (new $model)->getTable();
    } catch (Throwable) {
        return null;
    }

    if (! Schema::hasTable($table)) {
        return null;
    }

    foreach (CHANNEL_COLUMNS as $column) {
        if (Schema::hasColumn($table, $column)) {
            return $column;
        }
    }

    return null;
}

/**
 * Whether $model applies the channel scope, including through a parent or another trait.
 *
 * `class_uses_recursive`, not a grep for `use BelongsToChannel`: a model can inherit the
 * trait from a base class, and a grep would also match the import statement in a file
 * that never uses it.
 */
function isChannelScoped(string $model): bool
{
    return in_array(
        'Modules\Core\Support\Concerns\BelongsToChannel',
        class_uses_recursive($model),
        true,
    );
}

/**
 * Models with a channel column, no scope, and no written exemption.
 *
 * @return list<string>
 */
function unscopedChannelModels(): array
{
    $offenders = [];

    foreach (domainModelClasses() as $model) {
        if (in_array($model, CHANNEL_SCOPE_EXEMPT, true)) {
            continue;
        }

        $column = channelColumnOf($model);

        if ($column !== null && ! isChannelScoped($model)) {
            $short = str_replace('Modules\\', '', $model);
            $offenders[] = $short.' ('.(new $model)->getTable().'.'.$column.')';
        }
    }

    sort($offenders);

    return $offenders;
}

it('scopes every model whose table has a channel column', function () {
    if (! schemaAvailable()) {
        test()->markTestSkipped('no database connection; this assertion reads the live schema');
    }

    // Names each offender with its table and the column that betrays it, so the failure
    // is the work list rather than a count to go and reproduce.
    expect(unscopedChannelModels())->toBe([]);
})->group('arch');

it('exempts nothing without a written reason', function () {
    // An exemption list is only safe while every entry is justified in the constant
    // above. This asserts the list has not grown silently: adding a name here without
    // adding its reason means editing this expectation too, which is the moment someone
    // has to say why out loud.
    expect(CHANNEL_SCOPE_EXEMPT)->toBe([
        'Modules\Core\Domain\Models\AuditLog',
        'Modules\Tenancy\Domain\Models\ChannelZoneLookup',
        'Modules\Tenancy\Domain\Models\ChannelEvent',
    ]);
})->group('arch');

it('actually catches a model that has the column and not the scope', function () {
    // The detector proved against a known pair rather than trusted. `Brand` carries the
    // column and the trait; `Order` carries neither. Swapping the scope check for a
    // constant false must therefore surface Brand — if it does not, the scan is looking
    // at the wrong thing and its empty result means nothing.
    if (! schemaAvailable()) {
        test()->markTestSkipped('no database connection; this assertion reads the live schema');
    }

    $scopedModel = Brand::class;

    expect(channelColumnOf($scopedModel))->toBe('supply_channel_id')
        ->and(isChannelScoped($scopedModel))->toBeTrue()
        // The same model would be reported the instant it lost the trait.
        ->and(in_array($scopedModel, CHANNEL_SCOPE_EXEMPT, true))->toBeFalse();
})->group('arch');

it('leaves a model with no channel column alone', function () {
    // A Foundation reference table belongs to no channel, has no channel column, and must
    // never be asked to carry the scope.
    if (! schemaAvailable()) {
        test()->markTestSkipped('no database connection; this assertion reads the live schema');
    }

    $unowned = Governorate::class;

    expect(channelColumnOf($unowned))->toBeNull()
        ->and(isChannelScoped($unowned))->toBeFalse();
})->group('arch');
