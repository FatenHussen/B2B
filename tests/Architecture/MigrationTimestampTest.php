<?php

declare(strict_types=1);

/**
 * Every migration carries a unique timestamp; no two are equal.
 *
 * Laravel orders migrations by filename. Two files on the same stamp run in alphabetical
 * order of the rest of the name — which module sorts first, not which table the other
 * depends on — and the first foreign key across such a pair fails on a fresh database
 * with nothing in either file to say why.
 *
 * Sixteen files across six stamps predate the rule. They stay as they are: renaming a
 * migration that has already run makes every deployed database see it as new. They are
 * pinned here by name, exactly, the way ChannelScopeEscapeTest pins the escape inventory:
 * a new shared stamp fails the build, and a pair resolved by a later ticket asks for the
 * pin to shrink in the same commit. The same sixteen are recorded in docs/debt-ledger.md.
 */

use Illuminate\Support\Facades\File;

/**
 * Stamps carried by more than one file, with every file on each. Pinned; only shrinks.
 *
 * @var array<string, list<string>>
 */
const MIGRATION_STAMP_DUPLICATES = [
    '2026_08_08_125139' => [
        'database/migrations/2026_08_08_125139_create_media_table.php',
        'database/migrations/2026_08_08_125139_create_permission_tables.php',
    ],
    '2026_09_01_100000' => [
        'app-modules/access/database/migrations/2026_09_01_100000_enable_permission_teams.php',
        'app-modules/inventory/database/migrations/2026_09_01_100000_create_inventory_tables.php',
        'app-modules/reference/database/migrations/2026_09_01_100000_create_currencies_table.php',
    ],
    '2026_09_01_120000' => [
        'app-modules/identity/database/migrations/2026_09_01_120000_create_rep_field_tables.php',
        'app-modules/ordering/database/migrations/2026_09_01_120000_create_ordering_tables.php',
    ],
    '2026_09_01_130000' => [
        'app-modules/catalog/database/migrations/2026_09_01_130000_create_catalog_tables.php',
        'app-modules/fulfillment/database/migrations/2026_09_01_130000_create_fulfillment_tables.php',
    ],
    '2026_09_15_120000' => [
        'app-modules/content/database/migrations/2026_09_15_120000_create_content_tables.php',
        'app-modules/loyalty/database/migrations/2026_09_15_120000_create_loyalty_tables.php',
        'app-modules/notification/database/migrations/2026_09_15_120000_create_notification_tables.php',
        'app-modules/reporting/database/migrations/2026_09_15_120000_create_reporting_tables.php',
    ],
    '2026_09_16_130000' => [
        'app-modules/delivery/database/migrations/2026_09_16_130000_add_outcome_fields_to_deliveries.php',
        'app-modules/finance/database/migrations/2026_09_16_130000_add_rep_collection_columns.php',
        'app-modules/ordering/database/migrations/2026_09_16_130000_add_reason_to_sub_order_events.php',
    ],
];

/**
 * Every migration file the application loads, repo-relative with forward slashes: the
 * root directory plus each module's `database/migrations`, which is the only path any
 * module service provider passes to `loadMigrationsFrom`.
 *
 * @return list<string>
 */
function migrationFiles(): array
{
    $root = rtrim(str_replace('\\', '/', base_path()), '/').'/';
    $absolute = array_merge(
        File::glob(base_path('database/migrations/*.php')),
        File::glob(base_path('app-modules/*/database/migrations/*.php')),
    );

    $relative = array_map(
        fn (string $path): string => substr(str_replace('\\', '/', $path), strlen($root)),
        $absolute,
    );
    sort($relative);

    return $relative;
}

/**
 * The stamps shared by more than one of $paths, each with its files, sorted both ways.
 * A file with no stamp is skipped here and caught by its own assertion below.
 *
 * @param  list<string>  $paths
 * @return array<string, list<string>>
 */
function duplicateMigrationStamps(array $paths): array
{
    $byStamp = [];
    foreach ($paths as $path) {
        if (preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})_/', basename($path), $m) !== 1) {
            continue;
        }
        $byStamp[$m[1]][] = $path;
    }

    $shared = array_filter($byStamp, fn (array $files): bool => count($files) > 1);
    ksort($shared);
    foreach ($shared as &$files) {
        sort($files);
    }

    return $shared;
}

it('gives every migration a stamp no other migration carries, outside the pinned sixteen', function () {
    // Exactly, not "at most": a new shared stamp fails here, and a pair that a later
    // ticket resolves asks for its line to leave the pin in the same commit.
    expect(duplicateMigrationStamps(migrationFiles()))->toBe(MIGRATION_STAMP_DUPLICATES);
})->group('arch');

it('pins sixteen files across six stamps, the number CLAUDE.md states', function () {
    expect(MIGRATION_STAMP_DUPLICATES)->toHaveCount(6)
        ->and(array_sum(array_map('count', MIGRATION_STAMP_DUPLICATES)))->toBe(16);
})->group('arch');

it('names a file in the pin only while it still exists', function () {
    // A renamed or deleted file must leave the pin, or the pin describes a repository
    // that no longer exists.
    $files = migrationFiles();

    foreach (MIGRATION_STAMP_DUPLICATES as $stamp => $pinned) {
        foreach ($pinned as $path) {
            expect($files)->toContain($path);
            expect(str_starts_with(basename($path), $stamp.'_'))->toBeTrue("{$path} is pinned under {$stamp}");
        }
    }
})->group('arch');

it('finds a stamp on every migration file', function () {
    // An unstamped file sorts wherever PHP's string order puts it, which is worse than a
    // shared stamp: it does not even collide predictably.
    $unstamped = array_values(array_filter(
        migrationFiles(),
        fn (string $path): bool => preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_/', basename($path)) !== 1,
    ));

    expect($unstamped)->toBe([]);
})->group('arch');

it('actually detects a shared stamp', function () {
    $sample = [
        'app-modules/a/database/migrations/2026_09_17_090000_create_a.php',
        'app-modules/b/database/migrations/2026_09_17_090000_create_b.php',
        'app-modules/c/database/migrations/2026_09_17_090001_create_c.php',
        'database/migrations/not_a_migration.php',
    ];

    expect(duplicateMigrationStamps($sample))->toBe([
        '2026_09_17_090000' => [
            'app-modules/a/database/migrations/2026_09_17_090000_create_a.php',
            'app-modules/b/database/migrations/2026_09_17_090000_create_b.php',
        ],
    ])->and(duplicateMigrationStamps(['database/migrations/2026_09_17_090000_only.php']))->toBe([]);
})->group('arch');
