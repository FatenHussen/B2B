<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('picking_waves', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('channel_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->string('status', 16)->default('open');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamps();

            $table->index(['channel_id', 'warehouse_id']);
        });

        Schema::table('picking_lists', function (Blueprint $table): void {
            $table->unsignedBigInteger('wave_id')->nullable()->after('channel_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('picking_lists', function (Blueprint $table): void {
            $table->dropColumn('wave_id');
        });

        Schema::dropIfExists('picking_waves');
    }
};
