<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A sub-order records the currency and exchange rate it was priced at.
 *
 * **Declared scope extension.** BE-R09's working rules name `app-modules/reference`, and
 * this is Ordering. It is here because BR-AD-19 — a rate change never reprices an existing
 * order — cannot be proved otherwise: nothing in `sub_orders`, `sub_order_lines` or
 * `orders` held a rate, so a test that changed a rate and re-read an order would pass for
 * the wrong reason, proving only that no code reads rates at all. That is a green test
 * guarding an absence, which this repository has been bitten by three times.
 *
 * **This is not BE2-PRC05's freeze.** That ticket freezes the *unit price* against a price
 * list, so a later price list does not move an existing line. This freezes the *exchange
 * rate* against `fx_rates`, so a later FX row does not move an existing total. Two
 * independent facts about the same order: one can change without the other. BE2-PRC05 must
 * add its own column and must not rewrite these — recorded in BE-O14.
 *
 * Backward compatible. `currency_code` defaults to SYP, matching `orders.currency`, and
 * `fx_rate` defaults to `Money::FX_UNIT` — the identity rate, 1.0 — which is correct for
 * every existing row because every existing row is priced in the base currency.
 *
 * No logic reads these yet. They are written at creation and read by the BR-AD-19 test.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->char('currency_code', 3)->default('SYP')->after('total');

            // 1_000_000 is Money::FX_UNIT: a rate of exactly 1.0 at the fixed 10^6 scale
            // in rule 7. Not referenced as a constant because a migration must keep
            // meaning the same thing after the constant is edited.
            $table->unsignedBigInteger('fx_rate')->default(1_000_000)->after('currency_code');
        });
    }

    public function down(): void
    {
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->dropColumn(['currency_code', 'fx_rate']);
        });
    }
};
