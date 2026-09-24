<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_sync_cursors', function (Blueprint $table) {
            $table->id();
            $table->uuid('device_uuid');
            $table->unsignedBigInteger('warehouse_user_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('channel_id');
            $table->timestamp('pushed_at')->nullable();
            $table->timestamps();
            $table->unique('device_uuid');
            $table->index(['channel_id', 'warehouse_id']);
            $table->index('warehouse_user_id');
        });

        Schema::create('warehouse_sync_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('device_uuid');
            $table->unsignedBigInteger('warehouse_user_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('channel_id');
            $table->string('op_id', 80);
            $table->string('type', 64);
            $table->json('payload');
            $table->timestamp('client_ts')->nullable();
            $table->string('status', 16);
            $table->json('server_result')->nullable();
            $table->timestamps();
            $table->unique(['device_uuid', 'op_id']);
            $table->index(['channel_id', 'warehouse_id', 'status']);
            $table->index('warehouse_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_sync_operations');
        Schema::dropIfExists('warehouse_sync_cursors');
    }
};
