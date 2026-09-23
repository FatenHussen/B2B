<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PA-02 — expand channel_plans from the light BE-T04 shape into the catalog contract
 * (EP-AD-100A–D): pricing, features, on_exceed, trial, public flag, and guarded status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_plans', function (Blueprint $table) {
            $table->unsignedBigInteger('price_monthly')->default(0)->after('name');
            $table->unsignedBigInteger('price_yearly')->default(0)->after('price_monthly');
            $table->foreignId('currency_id')->nullable()->after('price_yearly');
            $table->json('features')->nullable()->after('limits');
            $table->string('on_exceed', 32)->default('block')->after('features');
            $table->unsignedSmallInteger('trial_days')->default(0)->after('on_exceed');
            $table->boolean('is_public')->default(true)->after('trial_days');
            $table->string('status', 16)->default('active')->after('is_public');
        });

        // Keep seeded rows consistent: status mirrors the previous is_active flag.
        DB::table('channel_plans')->where('is_active', true)->update(['status' => 'active']);
        DB::table('channel_plans')->where('is_active', false)->update(['status' => 'inactive']);
    }

    public function down(): void
    {
        Schema::table('channel_plans', function (Blueprint $table) {
            $table->dropColumn([
                'price_monthly',
                'price_yearly',
                'currency_id',
                'features',
                'on_exceed',
                'trial_days',
                'is_public',
                'status',
            ]);
        });
    }
};
