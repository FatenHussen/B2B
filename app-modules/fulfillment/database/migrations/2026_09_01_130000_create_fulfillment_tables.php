<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('picking_lists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_order_id')->unique();
            $table->unsignedBigInteger('warehouse_id')->index();
            $table->unsignedBigInteger('channel_id')->index();
            $table->string('status', 16)->index();
            $table->timestamp('due_at')->nullable();
            $table->unsignedInteger('pick_path_version')->default(1);
            $table->timestamps();
        });

        Schema::create('picking_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('picking_list_id')->index();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->unsignedInteger('qty_required');
            $table->unsignedInteger('qty_picked')->default(0);
            $table->unsignedBigInteger('location_id')->nullable();
            $table->string('barcode', 64)->nullable();
            $table->boolean('manual')->default(false);
            $table->string('shortage_reason', 32)->nullable();
        });

        Schema::create('packing_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('picking_list_id')->unique();
            $table->string('status', 16)->default('open');
            $table->unsignedInteger('packages_count')->default(0);
            $table->unsignedInteger('weight_gram')->default(0);
            $table->json('flags')->nullable();
            $table->json('mismatches')->nullable();
            $table->timestamps();
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('packing_job_id')->index();
            $table->string('package_no', 32);
            $table->string('qr_token', 64);
        });

        Schema::create('handovers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_id')->index();
            $table->unsignedBigInteger('rep_id')->index();
            $table->string('status', 32)->index();
            $table->string('temp_code', 4);
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('handover_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('handover_id')->index();
            $table->unsignedBigInteger('sub_order_id')->index();
            $table->json('package_ids')->nullable();
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_id')->index();
            $table->string('source', 16);
            $table->string('reference_no', 64)->nullable();
            $table->string('status', 16)->index();
            $table->timestamps();
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('goods_receipt_id')->index();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->unsignedInteger('qty_expected');
            $table->unsignedInteger('qty_received');
            $table->string('lot_no', 64)->nullable();
            $table->date('expiry_date')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();
        });

        Schema::create('stocktakes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_id')->index();
            $table->string('scope', 16);
            $table->string('status', 24)->index();
            $table->unsignedBigInteger('counted_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();
        });

        Schema::create('stocktake_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stocktake_id')->index();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->unsignedInteger('counted_qty');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocktake_lines');
        Schema::dropIfExists('stocktakes');
        Schema::dropIfExists('goods_receipt_lines');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('handover_items');
        Schema::dropIfExists('handovers');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('packing_jobs');
        Schema::dropIfExists('picking_lines');
        Schema::dropIfExists('picking_lists');
    }
};
