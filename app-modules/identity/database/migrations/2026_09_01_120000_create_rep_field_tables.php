<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rep_sourced_shops', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rep_id')->index();
            $table->string('shop_name');
            $table->string('owner_name');
            $table->string('phone', 20);
            $table->unsignedBigInteger('zone_id');
            $table->unsignedBigInteger('activity_type_id');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('client_op_id', 80);
            $table->string('status', 32)->default('pending_sync')->index();
            $table->unsignedBigInteger('retailer_id')->nullable()->index();
            $table->timestamps();
            $table->unique(['rep_id', 'client_op_id']);
        });

        Schema::create('rep_zone_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rep_id')->index();
            $table->unsignedBigInteger('zone_id');
            $table->text('note')->nullable();
            $table->string('status', 32)->default('pending_approval')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rep_zone_requests');
        Schema::dropIfExists('rep_sourced_shops');
    }
};
