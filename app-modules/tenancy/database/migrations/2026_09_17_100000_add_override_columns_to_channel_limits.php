<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BE-T12 — EP-AD-055.
 *
 * The five plain columns stay what provisioning wrote from the plan: the base. An
 * override lives beside them, nullable, and `temporary_until` / `reason` (already on the
 * table) now describe that override. Keeping base and override apart is what lets an
 * expired override revert by comparison at read time instead of by a job rewriting the
 * base back — see `ChannelLimitResolver`.
 *
 * Additive only: every existing row reads exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_limits', function (Blueprint $table): void {
            $table->unsignedInteger('override_users')->nullable()->after('storage_mb');
            $table->unsignedInteger('override_warehouses')->nullable()->after('override_users');
            $table->unsignedInteger('override_reps')->nullable()->after('override_warehouses');
            $table->unsignedInteger('override_skus')->nullable()->after('override_reps');
            $table->unsignedInteger('override_storage_mb')->nullable()->after('override_skus');
            $table->unsignedBigInteger('overridden_by')->nullable()->after('reason');
            $table->timestamp('overridden_at')->nullable()->after('overridden_by');
        });
    }

    public function down(): void
    {
        Schema::table('channel_limits', function (Blueprint $table): void {
            $table->dropColumn([
                'override_users',
                'override_warehouses',
                'override_reps',
                'override_skus',
                'override_storage_mb',
                'overridden_by',
                'overridden_at',
            ]);
        });
    }
};
