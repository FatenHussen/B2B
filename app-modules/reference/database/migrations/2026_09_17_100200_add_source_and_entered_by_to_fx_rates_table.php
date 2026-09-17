<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EP-AD-040 / EP-AD-043G carry `source` and `entered_by` on a rate. Both nullable, both
 * additive; `rate` stays the bigInteger at Money::FX_SCALE and no scale column appears
 * (rule 7).
 *
 * `effective_from` also moves from TIMESTAMP to DATETIME. MySQL gives the first NOT NULL
 * TIMESTAMP column of a table an implicit `ON UPDATE CURRENT_TIMESTAMP`, so closing a
 * rate — an UPDATE that sets only `effective_to` — silently rewrote its `effective_from`
 * to "now" and the rate's history lost its start. DATETIME carries no such behaviour and
 * the same values; nothing stored changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fx_rates', function (Blueprint $table): void {
            $table->dateTime('effective_from')->change();
            $table->string('source', 32)->nullable()->after('effective_to');
            $table->unsignedBigInteger('entered_by')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('fx_rates', function (Blueprint $table): void {
            $table->dropColumn(['source', 'entered_by']);
            $table->timestamp('effective_from')->change();
        });
    }
};
