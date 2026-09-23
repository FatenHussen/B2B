<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('description')->nullable();
            $table->boolean('enabled_globally')->default(false);
            $table->unsignedTinyInteger('rollout_percent')->default(100);
            $table->json('scopes')->nullable();
            $table->timestamp('planned_removal_at')->nullable();
            $table->timestamps();
        });

        Schema::create('feature_flag_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('feature_key', 64);
            $table->unsignedBigInteger('channel_id');
            $table->boolean('enabled');
            $table->string('reason', 500);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamps();
            $table->unique(['feature_key', 'channel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flag_overrides');
        Schema::dropIfExists('feature_flags');
    }
};
