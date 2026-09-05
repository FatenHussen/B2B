<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_order_id')->unique();
            $table->unsignedBigInteger('rep_id')->index();
            $table->string('status', 16)->index();
            $table->timestamps();
        });

        Schema::create('delivery_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_id')->index();
            $table->unsignedBigInteger('sub_order_line_id')->index();
            $table->unsignedInteger('qty_expected');
            $table->unsignedInteger('qty_delivered')->default(0);
            $table->string('action', 16)->default('pending');
            $table->string('reason')->nullable();
        });

        Schema::create('delivery_completions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_id')->unique();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->string('receipt_no', 32)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('signature')->nullable();
        });

        Schema::create('rep_location_pings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rep_id')->index();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->timestamp('at');
            $table->unsignedInteger('accuracy')->nullable();
        });

        Schema::create('rep_ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('retailer_id');
            $table->unsignedBigInteger('rep_id');
            $table->unsignedBigInteger('sub_order_id');
            $table->unsignedTinyInteger('stars');
            $table->string('note')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();
            $table->unique(['retailer_id', 'rep_id', 'sub_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rep_ratings');
        Schema::dropIfExists('rep_location_pings');
        Schema::dropIfExists('delivery_completions');
        Schema::dropIfExists('delivery_lines');
        Schema::dropIfExists('deliveries');
    }
};
