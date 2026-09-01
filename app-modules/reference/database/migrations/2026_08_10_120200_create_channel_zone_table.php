<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_zone', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supply_channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained()->cascadeOnDelete();
            $table->json('delivery_days')->nullable();
            $table->unsignedBigInteger('delivery_fee')->default(0);
            $table->unsignedBigInteger('min_order_value')->nullable();
            $table->timestamps();

            $table->unique(['supply_channel_id', 'zone_id']);
            $table->index(['supply_channel_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_zone');
    }
};
