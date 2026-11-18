<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table): void {
            $table->json('display')->nullable()->after('rules');
        });

        Schema::create('retailer_offer_favorites', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('retailer_id')->index();
            $table->unsignedBigInteger('offer_id')->index();
            $table->timestamps();
            $table->unique(['retailer_id', 'offer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retailer_offer_favorites');

        Schema::table('offers', function (Blueprint $table): void {
            $table->dropColumn('display');
        });
    }
};
