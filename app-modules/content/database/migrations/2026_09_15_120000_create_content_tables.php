<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_intros', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id')->unique();
            $table->boolean('enabled')->default(false);
            $table->string('text')->nullable();
            $table->string('media_type', 16)->nullable();
            $table->string('media_id')->nullable();
            $table->unsignedInteger('duration')->default(0);
            $table->json('targeting')->nullable();
            $table->timestamps();
            $table->index('supply_channel_id');
        });

        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id');
            $table->string('media_type', 16);
            $table->string('media_id');
            $table->json('link')->nullable();
            $table->json('placements');
            $table->json('targeting')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->unsignedInteger('weight')->default(1);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->timestamps();
            $table->index(['supply_channel_id', 'order']);
        });

        Schema::create('sliders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id');
            $table->string('name');
            $table->string('source', 32);
            $table->string('source_ref')->nullable();
            $table->string('algorithm', 64)->nullable();
            $table->json('placements')->nullable();
            $table->unsignedInteger('items_count')->default(12);
            $table->boolean('show_all_button')->default(false);
            $table->json('targeting')->nullable();
            $table->timestamps();
            $table->index('supply_channel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sliders');
        Schema::dropIfExists('banners');
        Schema::dropIfExists('channel_intros');
    }
};
