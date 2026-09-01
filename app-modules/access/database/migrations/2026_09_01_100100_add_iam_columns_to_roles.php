<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->string('label', 160)->nullable()->after('name');
            $table->string('system', 32)->default('platform')->after('guard_name');
            $table->string('status', 32)->default('active')->after('system');
            $table->boolean('is_builtin')->default(false)->after('status');
            $table->text('description')->nullable()->after('is_builtin');
            $table->unsignedBigInteger('created_by')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn(['label', 'system', 'status', 'is_builtin', 'description', 'created_by']);
        });
    }
};
