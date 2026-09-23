<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_retailer_redemptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offer_id');
            $table->unsignedBigInteger('retailer_id');
            $table->unsignedInteger('applied_count')->default(0);
            $table->unsignedInteger('qty_consumed')->default(0);
            $table->timestamps();

            $table->unique(['offer_id', 'retailer_id']);
        });

        Schema::create('offer_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offer_id');
            $table->unsignedBigInteger('retailer_id');
            $table->timestamp('viewed_at');
            $table->unique(['offer_id', 'retailer_id']);
            $table->index('offer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_views');
        Schema::dropIfExists('offer_retailer_redemptions');
    }
};
