<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_base_prices', function (Blueprint $table) {
            $table->unsignedTinyInteger('tax_percent')->default(0)->after('base_price');
        });
    }

    public function down(): void
    {
        Schema::table('product_base_prices', function (Blueprint $table) {
            $table->dropColumn('tax_percent');
        });
    }
};
