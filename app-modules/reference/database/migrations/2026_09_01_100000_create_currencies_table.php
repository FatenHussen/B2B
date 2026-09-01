<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->char('code', 3)->unique();
            $table->string('name');
            $table->string('symbol', 8)->nullable();
            $table->boolean('is_base')->default(false);
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
        });

        DB::table('currencies')->insert([
            'code' => 'SYP',
            'name' => 'الليرة السورية',
            'symbol' => 'ل.س',
            'is_base' => true,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
