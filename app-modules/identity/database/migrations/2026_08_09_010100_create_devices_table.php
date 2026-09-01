<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DOC-02 §8.1 — devices. One row per (user, device); a Sanctum token is bound
 * to a device so it can be revoked per-device on logout.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('device_uuid');
            $table->string('platform', 32)->nullable();  // android | ios | web | pwa
            $table->string('push_token')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'device_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
