<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id');
            $table->string('title');
            $table->text('body');
            $table->string('icon', 32)->nullable();
            $table->string('image')->nullable();
            $table->json('action')->nullable();
            $table->json('targeting');
            $table->json('channels');
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status', 16)->default('queued');
            $table->timestamps();
            $table->index(['supply_channel_id', 'status']);
        });

        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id');
            $table->string('event_key', 64);
            $table->string('title');
            $table->text('body')->nullable();
            $table->boolean('enabled')->default(true);
            $table->json('channels');
            $table->timestamps();
            $table->unique(['supply_channel_id', 'event_key']);
            $table->index(['supply_channel_id', 'event_key']);
        });

        Schema::create('notification_delivery_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id');
            $table->unsignedBigInteger('notification_id')->nullable();
            $table->unsignedBigInteger('recipient')->nullable();
            $table->string('template', 64)->nullable();
            $table->string('status', 16);
            $table->string('failure_reason')->nullable();
            $table->timestamp('at')->useCurrent();
            $table->index(['supply_channel_id', 'at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_delivery_log');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('channel_notifications');
    }
};
