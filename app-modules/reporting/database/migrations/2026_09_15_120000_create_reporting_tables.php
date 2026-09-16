<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id');
            $table->date('snapshot_date');
            $table->json('payload');
            $table->timestamps();
            $table->unique(['supply_channel_id', 'snapshot_date']);
            $table->index(['supply_channel_id', 'snapshot_date']);
        });

        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id');
            $table->string('job_id', 64)->unique();
            $table->string('type', 32);
            $table->string('format', 8);
            $table->json('filters')->nullable();
            $table->string('status', 16)->default('queued');
            $table->timestamps();
            $table->index(['supply_channel_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
        Schema::dropIfExists('daily_snapshots');
    }
};
