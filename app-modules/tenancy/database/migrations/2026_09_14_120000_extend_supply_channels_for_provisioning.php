<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BE-T04 (EP-AD-051): the fields a channel is created with, and the state it is
 * created in.
 *
 * Every new column is nullable or defaulted, so rows that exist today keep working.
 *
 * `status` now defaults to `provisioning`. `SupplyChannel` guards the column (rule 8),
 * so nothing assigns a status at creation and the column default is what a new row
 * gets — and `active` there would mean any row created without going through the
 * state machine is live. A channel becomes active only when provisioning finishes
 * (BE-T05), through `ChannelLifecycle`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supply_channels', function (Blueprint $table) {
            // Free text on purpose: the catalog shows `llc` and names no other value.
            // An enum guessed here would mean a migration the day the real list appears.
            $table->string('legal_form', 64)->nullable()->after('legal_name');
            $table->string('cr_number', 64)->nullable()->after('legal_form');
            $table->unsignedBigInteger('logo_media_id')->nullable()->after('email');
            $table->unsignedBigInteger('plan_id')->nullable()->index()->after('settings');
            $table->string('billing_cycle', 16)->nullable()->after('plan_id');
            $table->timestamp('trial_ends_at')->nullable()->after('billing_cycle');
            // A whole-number percentage, 0-100. Read by platform billing (BE4-BIL01);
            // on no money path today.
            $table->unsignedTinyInteger('custom_discount')->default(0)->after('trial_ends_at');
            $table->timestamp('provisioned_at')->nullable()->after('custom_discount');
        });

        Schema::table('supply_channels', function (Blueprint $table) {
            $table->string('status', 32)->default('provisioning')->change();
        });
    }

    public function down(): void
    {
        Schema::table('supply_channels', function (Blueprint $table) {
            $table->string('status', 32)->default('active')->change();
        });

        Schema::table('supply_channels', function (Blueprint $table) {
            $table->dropColumn([
                'legal_form', 'cr_number', 'logo_media_id', 'plan_id', 'billing_cycle',
                'trial_ends_at', 'custom_discount', 'provisioned_at',
            ]);
        });
    }
};
