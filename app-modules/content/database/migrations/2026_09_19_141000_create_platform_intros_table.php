<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_intros', function (Blueprint $table) {
            $table->id();
            $table->string('slot', 16)->unique();
            $table->boolean('enabled')->default(false);
            $table->string('text')->nullable();
            $table->string('media_type', 16)->nullable();
            $table->string('media_id')->nullable();
            $table->unsignedInteger('duration')->default(0);
            $table->json('targeting')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_intros');
    }
};
