<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retailer_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id');
            $table->string('name', 160);
            $table->timestamps();

            $table->index(['supply_channel_id', 'name']);
        });

        Schema::create('retailer_group_members', function (Blueprint $table) {
            $table->unsignedBigInteger('retailer_group_id');
            $table->unsignedBigInteger('retailer_id');
            $table->primary(['retailer_group_id', 'retailer_id']);
            $table->index('retailer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retailer_group_members');
        Schema::dropIfExists('retailer_groups');
    }
};
