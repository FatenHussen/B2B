<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('governorate_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('polygon')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->index(['governorate_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zones');
    }
};
