<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');
            $table->string('status', 16)->default('active')->index();
            $table->timestamps();
            $table->index(['owner_type', 'owner_id', 'status']);
        });

        Schema::create('cart_sections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cart_id')->index();
            $table->unsignedBigInteger('channel_id')->index();
            $table->string('opaque_ref', 32);
            $table->unsignedBigInteger('retailer_id')->nullable()->index();
            $table->string('note')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();
            $table->unique(['cart_id', 'channel_id', 'retailer_id']);
        });

        Schema::create('cart_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('section_id')->index();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->unsignedInteger('qty');
            $table->string('source', 16)->default('browse');
            $table->bigInteger('unit_price')->default(0);
            $table->bigInteger('discount')->default(0);
            $table->bigInteger('line_total')->default(0);
            $table->json('applied_rule')->nullable();
            $table->unsignedBigInteger('offer_id')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('retailer_id')->index();
            $table->string('source', 16);
            $table->string('order_no', 32)->unique();
            $table->string('status', 16)->default('pending');
            $table->string('currency', 8)->default('SYP');
            $table->timestamp('client_created_at')->nullable();
            $table->boolean('offline_created')->default(false);
            $table->bigInteger('repricing_diff')->nullable();
            $table->timestamps();
        });

        Schema::create('order_sections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('channel_id');
            $table->string('opaque_ref', 32);
            $table->string('note')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sub_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('channel_id')->index();
            $table->unsignedBigInteger('retailer_id')->index();
            $table->unsignedBigInteger('zone_id')->nullable()->index();
            $table->string('source', 16);
            $table->string('sub_order_no', 32)->unique();
            $table->string('status', 32)->index();
            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('discount')->default(0);
            $table->bigInteger('total')->default(0);
            $table->unsignedBigInteger('rep_id')->nullable()->index();
            $table->timestamp('scheduled_at')->nullable();
            $table->json('credit_check')->nullable();
            $table->timestamps();
        });

        Schema::create('sub_order_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_order_id')->index();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->unsignedInteger('qty');
            $table->bigInteger('unit_price');
            $table->bigInteger('discount')->default(0);
            $table->bigInteger('line_total');
            $table->json('applied_rule')->nullable();
            $table->unsignedBigInteger('offer_id')->nullable();
            $table->timestamps();
        });

        Schema::create('sub_order_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_order_id')->index();
            $table->string('stage', 32);
            $table->timestamp('at');
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
        });

        Schema::create('sub_order_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_order_id')->index();
            $table->unsignedBigInteger('rep_id')->index();
            $table->string('status', 16)->index();
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_order_assignments');
        Schema::dropIfExists('sub_order_events');
        Schema::dropIfExists('sub_order_lines');
        Schema::dropIfExists('sub_orders');
        Schema::dropIfExists('order_sections');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_lines');
        Schema::dropIfExists('cart_sections');
        Schema::dropIfExists('carts');
    }
};
