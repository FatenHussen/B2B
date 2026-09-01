<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'supply_channel_id')) {
            Schema::table('users', function (Blueprint $table) {
                // Null means a platform-level user who is not bound to one channel.
                $table->foreignId('supply_channel_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('supply_channels')
                    ->nullOnDelete();
            });

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('supply_channel_id')
                ->references('id')
                ->on('supply_channels')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['supply_channel_id']);
            $table->dropColumn('supply_channel_id');
        });
    }
};
