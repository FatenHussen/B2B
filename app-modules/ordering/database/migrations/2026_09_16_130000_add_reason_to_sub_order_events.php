<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_order_events', function (Blueprint $table) {
            $table->string('reason')->nullable()->after('actor_id');
        });
    }

    public function down(): void
    {
        Schema::table('sub_order_events', function (Blueprint $table) {
            $table->dropColumn('reason');
        });
    }
};
