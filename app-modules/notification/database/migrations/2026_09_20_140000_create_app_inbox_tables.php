<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id')->nullable();
            $table->string('recipient_kind', 16);
            $table->unsignedBigInteger('recipient_id');
            $table->string('icon', 32)->nullable();
            $table->string('title');
            $table->text('body');
            $table->json('action')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['channel_id', 'recipient_kind', 'recipient_id']);
            $table->index(['recipient_kind', 'recipient_id', 'dismissed_at']);
        });

        Schema::create('push_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('recipient_kind', 16);
            $table->unsignedBigInteger('recipient_id');
            $table->uuid('device_uuid');
            $table->string('platform', 16);
            $table->string('token');
            $table->timestamps();
            $table->unique('device_uuid');
            $table->index(['recipient_kind', 'recipient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_tokens');
        Schema::dropIfExists('app_notifications');
    }
};
