<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rep_duty_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rep_user_id')->unique();
            $table->boolean('on_duty')->default(false);
            $table->boolean('tracking_enabled')->default(false);
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rep_duty_states');
    }
};
