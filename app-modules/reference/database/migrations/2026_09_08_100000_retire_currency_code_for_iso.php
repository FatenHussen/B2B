<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `iso` becomes the only ISO 4217 column, and `is_display_currency` arrives.
 *
 * `code` and `iso` have held the same fact since `iso` was added — both `char(3)`, both
 * unique, both spelling `SYP`. The catalog names `iso` in every currency response and
 * never mentions `code`, so `iso` is the contract and `code` is the duplicate. BE-R08
 * retires it.
 *
 * Order matters: copy first, drop second. Every row is backfilled from `code` before the
 * column goes, and `iso` becomes NOT NULL only once nothing can be missing. Checked
 * before writing this: one row, `code = 'SYP'`, no empty value and no duplicate.
 *
 * `is_display_currency` is a **new column, not a rename of `is_base`**. They are different
 * facts and only one is in the contract. The display currency is switchable through
 * EP-AD-042G; the base currency is the unit every stored `bigInteger` amount is
 * denominated in under rule 7, appears in no contract, and is read internally by
 * `EloquentReferenceDirectory::baseCurrencyId()`. Renaming one into the other would hand
 * that endpoint the power to reinterpret every stored amount with no data migration.
 *
 * Not backward compatible in the strict sense — `code` disappears — which is why it is
 * paired in one migration with the backfill that makes `iso` complete. Nothing outside
 * this module reads `currencies`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Backfill before the constraint, so a row that somehow lacks `iso` cannot fail
        // the NOT NULL change halfway through.
        DB::table('currencies')->whereNull('iso')->update(['iso' => DB::raw('`code`')]);

        Schema::table('currencies', function (Blueprint $table) {
            $table->boolean('is_display_currency')->default(false)->after('is_base');
        });

        // The seeded base currency is also the display currency until someone says
        // otherwise; leaving every row false would mean the API reports no display
        // currency at all on a fresh install.
        DB::table('currencies')->where('is_base', true)->update(['is_display_currency' => true]);

        Schema::table('currencies', function (Blueprint $table) {
            $table->char('iso', 3)->nullable(false)->change();
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }

    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->char('code', 3)->nullable()->after('id');
        });

        DB::table('currencies')->update(['code' => DB::raw('`iso`')]);

        Schema::table('currencies', function (Blueprint $table) {
            $table->unique('code');
            $table->char('iso', 3)->nullable()->change();
            $table->dropColumn('is_display_currency');
        });
    }
};
