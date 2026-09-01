<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id')->index();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('logo_media_id')->nullable();
            $table->unsignedBigInteger('banner_media_id')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('brand_activity_types', function (Blueprint $table) {
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('activity_type_id');
            $table->primary(['brand_id', 'activity_type_id']);
        });

        Schema::create('brand_sliders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id')->index();
            $table->string('name');
            $table->string('source', 32);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedInteger('count')->default(0);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id')->index();
            $table->string('name');
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->unsignedBigInteger('root_category_id')->nullable()->index();
            $table->unsignedBigInteger('image_media_id')->nullable();
            $table->string('icon')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->string('status', 32)->default('active')->index();
            $table->unsignedTinyInteger('level');
            $table->timestamps();
        });

        Schema::create('category_activity_types', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('activity_type_id');
            $table->primary(['category_id', 'activity_type_id']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id')->index();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('sku');
            $table->unsignedBigInteger('brand_id')->nullable()->index();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->string('model_no')->nullable();
            $table->string('barcode')->nullable()->index();
            $table->string('status', 32)->default('draft')->index();
            $table->text('short_description')->nullable();
            $table->text('long_description')->nullable();
            $table->unsignedBigInteger('sale_unit_id')->nullable();
            $table->unsignedInteger('min_order_qty')->default(1);
            $table->unsignedInteger('order_multiple')->default(1);
            $table->unsignedInteger('weight_gram')->nullable();
            $table->boolean('tracked')->default(false);
            $table->unsignedInteger('reorder_point')->default(0);
            $table->boolean('allow_backorder')->default(false);
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->json('tags')->nullable();
            $table->timestamps();
            $table->unique(['supply_channel_id', 'sku']);
        });

        Schema::create('product_specs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->string('key');
            $table->string('value');
            $table->unsignedInteger('order')->default(0);
        });

        Schema::create('product_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('media_id');
            $table->string('role', 32);
            $table->unsignedInteger('order')->default(0);
        });

        Schema::create('product_unit_factors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('from_unit_id');
            $table->unsignedBigInteger('to_unit_id');
            $table->unsignedInteger('factor');
        });

        Schema::create('product_zones', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('zone_id');
            $table->primary(['product_id', 'zone_id']);
        });

        Schema::create('product_activity_types', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('activity_type_id');
            $table->primary(['product_id', 'activity_type_id']);
        });

        Schema::create('product_retailer_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('group_id');
            $table->primary(['product_id', 'group_id']);
        });

        Schema::create('product_slider_tags', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id');
            $table->string('slider_key', 64);
            $table->primary(['product_id', 'slider_key']);
        });

        Schema::create('product_variant_axes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->string('name');
            $table->unsignedInteger('order')->default(0);
        });

        Schema::create('product_variant_axis_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('axis_id')->index();
            $table->string('value');
            $table->unsignedInteger('order')->default(0);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->string('sku');
            $table->string('barcode')->nullable();
            $table->unsignedBigInteger('image_media_id')->nullable();
            $table->json('combination');
            $table->string('status', 32)->default('active');
            $table->unique(['product_id', 'sku']);
            $table->timestamps();
        });

        Schema::create('retailer_product_favorites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('retailer_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->timestamps();
            $table->unique(['retailer_id', 'product_id']);
        });

        Schema::create('retailer_shortages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('retailer_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retailer_shortages');
        Schema::dropIfExists('retailer_product_favorites');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_variant_axis_values');
        Schema::dropIfExists('product_variant_axes');
        Schema::dropIfExists('product_slider_tags');
        Schema::dropIfExists('product_retailer_groups');
        Schema::dropIfExists('product_activity_types');
        Schema::dropIfExists('product_zones');
        Schema::dropIfExists('product_unit_factors');
        Schema::dropIfExists('product_media');
        Schema::dropIfExists('product_specs');
        Schema::dropIfExists('products');
        Schema::dropIfExists('category_activity_types');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('brand_sliders');
        Schema::dropIfExists('brand_activity_types');
        Schema::dropIfExists('brands');
    }
};
