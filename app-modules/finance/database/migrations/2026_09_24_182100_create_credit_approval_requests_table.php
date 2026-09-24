<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_approval_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id');
            $table->unsignedBigInteger('retailer_id');
            $table->bigInteger('amount_minor');
            $table->bigInteger('outstanding_at_request');
            $table->bigInteger('credit_limit_at_request');
            $table->string('status', 32)->default('pending');
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['supply_channel_id', 'status']);
            $table->index(['retailer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_approval_requests');
    }
};
