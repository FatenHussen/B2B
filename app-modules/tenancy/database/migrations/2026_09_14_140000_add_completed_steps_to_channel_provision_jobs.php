<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BE-T05: completed_steps lets a retry skip work that already landed, without
 * duplicating a warehouse or a manager invite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_provision_jobs', function (Blueprint $table) {
            $table->json('completed_steps')->nullable()->after('payload');
        });
    }

    public function down(): void
    {
        Schema::table('channel_provision_jobs', function (Blueprint $table) {
            $table->dropColumn('completed_steps');
        });
    }
};
