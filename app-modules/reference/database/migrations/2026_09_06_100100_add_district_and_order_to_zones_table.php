<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zones gain a district name and an explicit ordering.
 *
 * `status` is deliberately absent here: `zones` has carried one since it was created,
 * cast to `ZoneStatus` (active/inactive — not `RefStatus`, whose second case is
 * `disabled`). That existing column is why BE-R03 can turn its hard delete into a real
 * disable without a migration, while governorates needed one.
 *
 * Backward compatible: `district` is nullable and `order` defaults to 0. Nothing reads
 * either column yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->string('district')->nullable()->after('name');
            $table->unsignedInteger('order')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->dropColumn(['district', 'order']);
        });
    }
};
