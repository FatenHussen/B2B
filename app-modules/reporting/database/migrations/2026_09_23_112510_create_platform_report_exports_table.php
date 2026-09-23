<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_report_exports', function (Blueprint $table) {
            $table->id();
            $table->string('job_id', 64)->unique();
            $table->string('type', 64);
            $table->string('status', 16)->default('queued');
            $table->string('download_url')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_report_exports');
    }
};
