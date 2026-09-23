<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_blocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id');
            $table->string('type', 32);
            $table->string('title')->nullable();
            $table->json('payload')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamp('active_from')->nullable();
            $table->timestamp('active_to')->nullable();
            $table->json('targeting')->nullable();
            $table->timestamps();
            $table->index(['supply_channel_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_blocks');
    }
};
