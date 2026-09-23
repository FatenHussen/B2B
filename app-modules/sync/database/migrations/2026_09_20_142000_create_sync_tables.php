<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_cursors', function (Blueprint $table) {
            $table->id();
            $table->uuid('device_uuid');
            $table->unsignedBigInteger('app_user_id');
            $table->string('cursor')->nullable();
            $table->timestamp('pulled_at')->nullable();
            $table->timestamp('pushed_at')->nullable();
            $table->timestamps();
            $table->unique('device_uuid');
            $table->index('app_user_id');
        });

        Schema::create('sync_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('device_uuid');
            $table->unsignedBigInteger('app_user_id');
            $table->string('op_id', 80);
            $table->string('type', 64);
            $table->json('payload');
            $table->timestamp('client_ts')->nullable();
            $table->string('status', 16);
            $table->json('server_result')->nullable();
            $table->timestamps();
            $table->unique(['device_uuid', 'op_id']);
            $table->index(['device_uuid', 'status']);
            $table->index('app_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_operations');
        Schema::dropIfExists('sync_cursors');
    }
};
