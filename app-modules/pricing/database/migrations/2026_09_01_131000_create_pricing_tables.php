<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_base_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id')->index();
            $table->unsignedBigInteger('product_id')->unique();
            $table->unsignedBigInteger('currency_id');
            $table->string('type', 16);
            $table->bigInteger('base_price');
            $table->timestamps();
        });

        Schema::create('product_qty_tiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedInteger('from_qty');
            $table->unsignedInteger('to_qty')->nullable();
            $table->bigInteger('price');
        });

        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id')->index();
            $table->string('name');
            $table->string('type', 16);
            $table->string('status', 32)->default('active')->index();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->unsignedBigInteger('retailer_id')->nullable();
            $table->string('adjustment_mode', 16);
            $table->integer('adjustment_value');
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('price_list_zones', function (Blueprint $table) {
            $table->unsignedBigInteger('price_list_id');
            $table->unsignedBigInteger('zone_id');
            $table->primary(['price_list_id', 'zone_id']);
        });

        Schema::create('price_list_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('price_list_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->bigInteger('override_price')->nullable();
        });

        Schema::create('price_list_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('price_list_id')->index();
            $table->timestamp('effective_from');
            $table->json('payload');
            $table->string('job_id')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });

        Schema::create('price_change_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->bigInteger('before');
            $table->bigInteger('after');
            $table->string('reason')->nullable();
            $table->timestamp('at');
        });

        Schema::create('rep_commercial_limits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');
            $table->unsignedBigInteger('rep_id');
            $table->unsignedInteger('max_discount_percent')->default(0);
            $table->bigInteger('max_cash_hold')->default(0);
            $table->timestamps();
            $table->unique(['channel_id', 'rep_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rep_commercial_limits');
        Schema::dropIfExists('price_change_logs');
        Schema::dropIfExists('price_list_schedules');
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_list_zones');
        Schema::dropIfExists('price_lists');
        Schema::dropIfExists('product_qty_tiers');
        Schema::dropIfExists('product_base_prices');
    }
};
