<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_media_libraries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id')->unique();
            $table->timestamps();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('length_mm')->nullable()->after('weight_gram');
            $table->unsignedInteger('width_mm')->nullable()->after('length_mm');
            $table->unsignedInteger('height_mm')->nullable()->after('width_mm');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['length_mm', 'width_mm', 'height_mm']);
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('description');
        });
        Schema::dropIfExists('channel_media_libraries');
    }
};
