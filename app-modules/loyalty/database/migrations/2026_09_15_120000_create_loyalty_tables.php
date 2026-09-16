<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_rule_sets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id')->unique();
            $table->json('retailer_rules');
            $table->json('rep_rules');
            $table->json('tiers');
            $table->timestamps();
            $table->index('supply_channel_id');
        });

        Schema::create('loyalty_rewards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id');
            $table->string('name');
            $table->unsignedInteger('points_cost');
            $table->unsignedInteger('stock')->default(0);
            $table->date('expires_at')->nullable();
            $table->timestamps();
            $table->index('supply_channel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_rewards');
        Schema::dropIfExists('loyalty_rule_sets');
    }
};
