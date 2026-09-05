<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id')->index();
            $table->unsignedBigInteger('warehouse_id')->index();
            $table->string('aisle', 32);
            $table->string('shelf', 32);
            $table->string('code', 64);
            $table->timestamps();
            $table->unique(['warehouse_id', 'code']);
        });

        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id')->index();
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->default(0);
            $table->unsignedBigInteger('on_hand')->default(0);
            $table->unsignedBigInteger('reserved')->default(0);
            $table->unsignedBigInteger('in_transit')->default(0);
            $table->unsignedBigInteger('damaged')->default(0);
            $table->timestamps();
            $table->unique(['warehouse_id', 'product_id', 'variant_id'], 'stock_balances_wh_sku_unique');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id')->index();
            $table->unsignedBigInteger('warehouse_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('variant_id')->default(0);
            $table->string('type', 32)->index();
            $table->bigInteger('qty_delta');
            $table->unsignedBigInteger('qty_before');
            $table->unsignedBigInteger('qty_after');
            $table->string('reason')->nullable();
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('ref_type', 64)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->timestamp('at')->useCurrent();
            $table->index(['warehouse_id', 'product_id']);
        });

        Schema::create('stock_reorder_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id')->index();
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->default(0);
            $table->unsignedInteger('point');
            $table->timestamps();
            $table->unique(['warehouse_id', 'product_id', 'variant_id'], 'stock_reorder_wh_sku_unique');
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id')->index();
            $table->unsignedBigInteger('from_warehouse_id');
            $table->unsignedBigInteger('to_warehouse_id');
            $table->string('status', 16)->index();
            $table->timestamps();
        });

        Schema::create('stock_transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_transfer_id')->index();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->default(0);
            $table->unsignedInteger('qty');
        });

        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id')->index();
            $table->unsignedBigInteger('sub_order_id')->index();
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->default(0);
            $table->unsignedInteger('qty');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('stock_transfer_lines');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('stock_reorder_points');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('warehouse_locations');
    }
};
