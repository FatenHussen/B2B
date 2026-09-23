<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_applications', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('legal_form', 32)->nullable();
            $table->string('cr_number', 64)->nullable();
            $table->json('documents')->nullable();
            $table->json('contact')->nullable();
            $table->string('status', 32)->default('under_review');
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('reason', 500)->nullable();
            $table->unsignedBigInteger('channel_id')->nullable();
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_applications');
    }
};
