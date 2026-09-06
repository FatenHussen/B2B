<?php

declare(strict_types=1);

/**
 * No DDL inside a RefreshDatabase test.
 *
 * MySQL commits implicitly on `CREATE TABLE`, `ALTER TABLE`, `DROP TABLE` and their
 * relatives. `tests/Pest.php` wraps every `tests/Feature` test in a transaction it
 * intends to roll back, so a single `Schema::create` in a `beforeEach` ends that
 * transaction, keeps whatever the test wrote, and leaves the connection's nesting count
 * out of step with reality.
 *
 * What that looks like from the outside is the reason this rule is enforced rather than
 * remembered: the file containing the DDL passes. Other files — chosen by test ordering,
 * so a different set on each run — fail with "Table 'users' doesn't exist" or "Table
 * 'password_reset_tokens' already exists". It reads as a broken migration or a flaky
 * database, and it cost a batch of migration work to trace back to one line in one
 * unrelated test. `tests/Unit/TenantIsolationTest.php` is where that line used to live.
 *
 * Scope. The hazard is exactly the transaction, so the rule covers exactly the
 * directories that get one. Per `tests/Pest.php` that is `tests/Feature` alone: `Unit`
 * and `Architecture` are extended without `RefreshDatabase`, so DDL there commits
 * nothing that was meant to survive. That exemption is also what lets this file quote
 * violating code in its own examples without tripping itself.
 *
 * A test that genuinely needs its own table belongs in `tests/Unit`, where it creates
 * and drops it explicitly, or the table belongs in a migration.
 */

use Symfony\Component\Finder\Finder;

/**
 * Schema builder calls that emit DDL. `Schema::table` is included: adding a column mid
 * test commits just as hard as creating the table.
 */
const SCHEMA_DDL_METHODS = [
    'create', 'createDatabase', 'drop', 'dropDatabaseIfExists', 'dropIfExists',
    'dropAllTables', 'dropAllViews', 'dropColumns', 'rename', 'table',
];

/** Statements that are DDL when handed to the driver as raw SQL. */
const RAW_DDL_KEYWORDS = ['CREATE', 'ALTER', 'DROP', 'RENAME', 'TRUNCATE'];

/**
 * Every DDL call site in $code, as the fragment that matched.
 *
 * @return list<string>
 */
function ddlCallSites(string $code): array
{
    $found = [];

    // Schema::create(...), and the same through a named connection:
    // Schema::connection('mysql')->dropIfExists(...)
    $methods = implode('|', SCHEMA_DDL_METHODS);
    preg_match_all(
        '/Schema::(?:connection\s*\([^)]*\)\s*->)?('.$methods.')\s*\(/',
        $code,
        $schema,
        PREG_SET_ORDER,
    );

    foreach ($schema as $call) {
        $found[] = 'Schema::'.$call[1].'()';
    }

    // DB::statement('ALTER TABLE ...') and DB::unprepared(<<<SQL CREATE TABLE ...).
    // Only raw SQL whose first keyword is visible at the call site is caught; a DDL
    // string assembled in a variable is not, and does not need to be — the point is to
    // make the ordinary way of doing this impossible, not to defeat someone determined.
    $keywords = implode('|', RAW_DDL_KEYWORDS);
    preg_match_all(
        '/DB::(?:statement|unprepared)\s*\(\s*(?:[\'"]|<<<[\'"]?\w+\s*\n\s*)('.$keywords.')\b/i',
        $code,
        $raw,
        PREG_SET_ORDER,
    );

    foreach ($raw as $call) {
        $found[] = 'DB::statement('.strtoupper($call[1]).' ...)';
    }

    return array_values(array_unique($found));
}

it('runs no DDL inside a test that RefreshDatabase wraps in a transaction', function () {
    $offenders = [];

    $sources = Finder::create()->files()->name('*.php')->in(base_path('tests/Feature'));

    foreach ($sources as $source) {
        $calls = ddlCallSites($source->getContents());

        if ($calls !== []) {
            $file = str_replace(base_path().DIRECTORY_SEPARATOR, '', $source->getPathname());
            $offenders[$file] = $calls;
        }
    }

    expect($offenders)->toBe([]);
})->group('arch');

it('actually catches DDL written the ordinary ways', function () {
    $violating = <<<'PHP'
    <?php

    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    beforeEach(function () {
        Schema::create('fixtures', fn ($t) => $t->id());
        Schema::connection('mysql')->dropIfExists('leftovers');
    });

    it('adds a column halfway through', function () {
        Schema::table('fixtures', fn ($t) => $t->string('extra'));
        DB::statement('ALTER TABLE fixtures ADD COLUMN late INT');
    });
    PHP;

    expect(ddlCallSites($violating))->toBe([
        'Schema::create()',
        'Schema::dropIfExists()',
        'Schema::table()',
        'DB::statement(ALTER ...)',
    ]);
})->group('arch');

it('leaves a Feature test that only reads and writes rows alone', function () {
    $compliant = <<<'PHP'
    <?php

    use Illuminate\Support\Facades\DB;
    use Modules\Reference\Domain\Models\Governorate;

    it('lists governorates', function () {
        Governorate::factory()->count(3)->create();

        DB::table('governorates')->where('code', 'DAM')->update(['order' => 1]);
        DB::statement('SELECT 1');

        $this->getJson('/api/v1/governorates')->assertOk()->assertJsonCount(3, 'data');
    });
    PHP;

    expect(ddlCallSites($compliant))->toBe([]);
})->group('arch');
