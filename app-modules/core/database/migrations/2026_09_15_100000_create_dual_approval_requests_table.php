<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dual_approval_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id');
            $table->string('permission', 64);
            $table->string('action', 64);
            $table->json('payload');
            $table->char('payload_hash', 64);
            $table->string('status', 16)->default('pending');
            $table->unsignedBigInteger('requester_id');
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->index(['supply_channel_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dual_approval_requests');
    }
};
