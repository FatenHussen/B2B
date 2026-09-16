<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('zone_id')->nullable()->after('sub_order_id');
            $table->unsignedBigInteger('rep_id')->nullable()->after('zone_id');
            $table->index(['channel_id', 'status']);
            $table->index(['channel_id', 'type']);
            $table->index(['channel_id', 'zone_id']);
            $table->index(['channel_id', 'rep_id']);
        });
    }

    public function down(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $table->dropIndex(['channel_id', 'status']);
            $table->dropIndex(['channel_id', 'type']);
            $table->dropIndex(['channel_id', 'zone_id']);
            $table->dropIndex(['channel_id', 'rep_id']);
            $table->dropColumn(['zone_id', 'rep_id']);
        });
    }
};
