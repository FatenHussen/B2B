<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->string('app', 32);
            $table->string('platform', 32);
            $table->string('version', 32);
            $table->string('build', 32)->nullable();
            $table->string('min_supported', 32)->nullable();
            $table->text('release_notes')->nullable();
            $table->unsignedTinyInteger('rollout')->default(100);
            $table->string('store_url')->nullable();
            $table->boolean('force_update')->default(false);
            $table->timestamps();
            $table->index(['app', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};
