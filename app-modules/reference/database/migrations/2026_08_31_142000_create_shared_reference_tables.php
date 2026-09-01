<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('root_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->string('image')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('activity_type_root_category', function (Blueprint $table) {
            $table->unsignedBigInteger('activity_type_id');
            $table->unsignedBigInteger('root_category_id');
            $table->primary(['activity_type_id', 'root_category_id']);
        });

        Schema::create('sale_units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('abbr', 16)->nullable();
            $table->unsignedInteger('default_factor')->default(1);
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('equipments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipments');
        Schema::dropIfExists('sale_units');
        Schema::dropIfExists('activity_type_root_category');
        Schema::dropIfExists('root_categories');
        Schema::dropIfExists('activity_types');
    }
};
