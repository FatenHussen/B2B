<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Currencies gain the two fields the API catalog names but the table never had.
 *
 * `decimals` defaults to 0, which is the correct value for the one row that exists: the
 * catalog gives SYP `"decimals": 0` and USD `"decimals": 2`, so the default matches the
 * seeded base currency rather than the ISO 4217 general case. Nothing reads the column
 * yet; BE-R08 should require it explicitly on create rather than let this default stand
 * in for a real answer, because a currency silently declared to have zero decimals
 * misreads every amount stored against it.
 *
 * `iso` is nullable on purpose. `Currency::$fillable` does not carry it, so any existing
 * factory or insert that omits it would fail against a NOT NULL column, and MySQL allows
 * repeated NULLs under a unique index. Existing rows are backfilled from `code` below.
 *
 * Which leaves a duplication BE-R08 has to settle rather than inherit: `code` is already
 * `char(3)` unique holding exactly the ISO code — `SYP` — while the catalog calls the
 * field `iso` in every response body. Two columns now hold the same fact, and a row
 * created after this migration gets `iso = null` unless something writes it. Either the
 * resource writes `iso` and `code` is dropped, or `iso` is the read alias and `code`
 * stays the written one. This migration takes no position; it only stops the column from
 * being missing.
 *
 * `is_base` is deliberately untouched — see the batch report. It is not a spelling of
 * `is_display_currency`: BE-R09 switches the display currency through an endpoint, while
 * the base currency is the unit every stored `bigInteger` amount is denominated in under
 * rule 7. Making one settable as the other would re-price the ledger with no data
 * migration. `is_display_currency` belongs beside it as a second column, in BE-R09.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->unsignedTinyInteger('decimals')->default(0)->after('symbol');
            $table->char('iso', 3)->nullable()->unique()->after('code');
        });

        DB::table('currencies')->whereNull('iso')->update(['iso' => DB::raw('`code`')]);
    }

    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropUnique(['iso']);
            $table->dropColumn(['decimals', 'iso']);
        });
    }
};
