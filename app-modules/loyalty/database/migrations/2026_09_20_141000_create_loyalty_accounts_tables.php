<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id');
            $table->string('owner_kind', 16);
            $table->unsignedBigInteger('owner_id');
            $table->unsignedInteger('balance')->default(0);
            $table->string('tier', 32)->default('bronze');
            $table->timestamps();
            $table->unique(['supply_channel_id', 'owner_kind', 'owner_id']);
            $table->index(['supply_channel_id', 'owner_kind', 'owner_id']);
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id');
            $table->unsignedBigInteger('account_id');
            $table->string('type', 16);
            $table->integer('points');
            $table->string('reason', 64);
            $table->string('reference_type', 32)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamps();
            $table->index(['supply_channel_id', 'account_id']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('loyalty_redemptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id');
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('reward_id');
            $table->string('redemption_no', 32);
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->unique('redemption_no');
            $table->index(['supply_channel_id', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_redemptions');
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('loyalty_accounts');
    }
};
