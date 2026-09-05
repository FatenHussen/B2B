<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id')->index();
            $table->unsignedBigInteger('sub_order_id')->unique();
            $table->unsignedBigInteger('retailer_id')->index();
            $table->unsignedBigInteger('rep_id')->nullable();
            $table->string('no', 32)->unique();
            $table->bigInteger('total');
            $table->string('status', 16)->default('open');
            $table->timestamps();
        });

        Schema::create('receipt_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id')->unique();
            $table->unsignedBigInteger('next_no')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_counters');
        Schema::dropIfExists('invoices');
    }
};
