<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exchange rates between two currencies over a period.
 *
 * `rate` is a `bigInteger`, never a float — rule 7 holds on any money path, and an
 * exchange rate multiplied into an amount is squarely on one. What the integer counts is
 * not decided here: the ticket specifies five columns and this migration adds exactly
 * those, so the scale is implicit. Before anything reads the column, BE-R08 has to say
 * whether a rate is fixed at a known number of decimal places or carries its own scale
 * in a sixth column. An integer rate with an unwritten scale is a rounding bug waiting
 * for its first conversion, and it is cheaper to answer now than to re-denominate later.
 *
 * `effective_to` is nullable so the current rate can be open ended, matching
 * `price_lists` in Modules\Pricing, which uses nullable timestamps for the same pair.
 *
 * Both foreign keys point at `currencies`, a table in this same module, so a real
 * constraint is allowed here — rule 4 forbids relations across a module boundary, not
 * inside one. Neither cascades on delete: reference entities are never hard deleted
 * under rule 12, and a currency with rates against it should refuse to disappear.
 *
 * The composite index leads on `from_currency_id`, so it also serves a lookup by source
 * currency alone, and by the pair without a date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fx_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_currency_id')->constrained('currencies');
            $table->foreignId('to_currency_id')->constrained('currencies');
            $table->bigInteger('rate');
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->timestamps();

            $table->index(['from_currency_id', 'to_currency_id', 'effective_from'], 'fx_rates_pair_effective_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_rates');
    }
};
