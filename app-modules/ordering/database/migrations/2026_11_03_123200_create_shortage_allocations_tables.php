<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shortage_allocations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('channel_id')->index();
            $table->unsignedBigInteger('warehouse_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('variant_id')->nullable()->index();
            $table->unsignedInteger('qty_available');
            $table->string('reason', 32);
            $table->string('status', 32)->default('open');
            $table->timestamps();

            $table->index(['channel_id', 'product_id', 'variant_id']);
        });

        Schema::create('shortage_allocation_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('shortage_allocation_id')->index();
            $table->unsignedBigInteger('sub_order_id')->index();
            $table->unsignedBigInteger('sub_order_line_id')->index();
            $table->unsignedInteger('qty_requested');
            $table->unsignedInteger('qty_allocated');
            $table->string('status', 32)->default('awaiting_retailer');
            $table->timestamps();

            $table->index(['sub_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shortage_allocation_lines');
        Schema::dropIfExists('shortage_allocations');
    }
};
