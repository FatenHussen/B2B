<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id')->index();
            $table->string('name');
            $table->string('type', 32);
            $table->text('description')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->boolean('stackable')->default(false);
            $table->integer('priority')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('total_qty')->nullable();
            $table->unsignedInteger('per_retailer_max')->nullable();
            $table->unsignedInteger('per_order_max')->nullable();
            $table->bigInteger('min_invoice_value')->default(0);
            $table->unsignedInteger('min_items')->nullable();
            $table->string('targeting_scope', 32)->default('all');
            $table->json('rules')->nullable();
            $table->string('stop_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('offer_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offer_id')->index();
            $table->unsignedBigInteger('media_id');
            $table->unsignedInteger('order')->default(0);
        });

        Schema::create('offer_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offer_id')->index();
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('qty')->default(1);
        });

        Schema::create('offer_rewards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offer_id')->index();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedInteger('qty')->default(1);
            $table->unsignedInteger('discount_percent')->nullable();
            $table->bigInteger('discount_amount')->nullable();
        });

        Schema::create('offer_activity_types', function (Blueprint $table) {
            $table->unsignedBigInteger('offer_id');
            $table->unsignedBigInteger('activity_type_id');
            $table->primary(['offer_id', 'activity_type_id']);
        });

        Schema::create('offer_zones', function (Blueprint $table) {
            $table->unsignedBigInteger('offer_id');
            $table->unsignedBigInteger('zone_id');
            $table->primary(['offer_id', 'zone_id']);
        });

        Schema::create('offer_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('offer_id');
            $table->unsignedBigInteger('group_id');
            $table->primary(['offer_id', 'group_id']);
        });

        Schema::create('offer_retailers', function (Blueprint $table) {
            $table->unsignedBigInteger('offer_id');
            $table->unsignedBigInteger('retailer_id');
            $table->primary(['offer_id', 'retailer_id']);
        });

        Schema::create('offer_redemptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offer_id')->unique();
            $table->unsignedInteger('applied_count')->default(0);
            $table->unsignedInteger('qty_consumed')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_redemptions');
        Schema::dropIfExists('offer_retailers');
        Schema::dropIfExists('offer_groups');
        Schema::dropIfExists('offer_zones');
        Schema::dropIfExists('offer_activity_types');
        Schema::dropIfExists('offer_rewards');
        Schema::dropIfExists('offer_components');
        Schema::dropIfExists('offer_media');
        Schema::dropIfExists('offers');
    }
};
