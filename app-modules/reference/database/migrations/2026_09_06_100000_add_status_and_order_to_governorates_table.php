<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Governorates gain the two columns every other shared reference table already has.
 *
 * `status` is what CLAUDE.md rule 12 needs before `DELETE /api/v1/governorates/{id}` can
 * stop hard deleting. That route is gated on `ad.refs.disable` today while its controller
 * still calls `->delete()`, a mismatch left deliberately in the permission batch because
 * closing it needed this column. Nothing reads the column yet; BE-R02 changes the verb.
 *
 * Backward compatible: both columns have defaults, so every existing row and every insert
 * that does not name them keeps working. `RefStatus` is the PHP enum for the string, per
 * CLAUDE.md — "States | PHP Enum, string in the database" — matching `activity_types`,
 * `root_categories`, `sale_units`, `equipments` and `currencies`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('governorates', function (Blueprint $table) {
            $table->string('status', 32)->default('active')->index()->after('code');
            $table->unsignedInteger('order')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('governorates', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'order']);
        });
    }
};
