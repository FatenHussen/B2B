<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id')->index();
            $table->unsignedBigInteger('sub_order_id')->index();
            $table->string('requester_type');
            $table->unsignedBigInteger('requester_id');
            $table->string('type', 16);
            $table->string('status', 16)->index();
            $table->string('request_no', 32)->unique();
            $table->timestamps();
        });

        Schema::create('return_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('return_request_id')->index();
            $table->unsignedBigInteger('line_id');
            $table->unsignedInteger('qty');
            $table->string('reason');
            $table->json('photos')->nullable();
            $table->string('condition', 16)->nullable();
        });

        Schema::create('return_decisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('return_request_id')->index();
            $table->string('decision', 16);
            $table->string('reason')->nullable();
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_decisions');
        Schema::dropIfExists('return_lines');
        Schema::dropIfExists('return_requests');
    }
};
