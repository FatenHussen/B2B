<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_lots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id')->index();
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('lot_no', 64)->nullable();
            $table->date('expiry_date')->nullable();
            $table->unsignedInteger('qty')->default(0);
            $table->timestamps();

            $table->index(['supply_channel_id', 'warehouse_id', 'product_id', 'variant_id'], 'stock_lots_channel_wh_product_idx');
            $table->index(['warehouse_id', 'product_id', 'expiry_date'], 'stock_lots_fefo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_lots');
    }
};
