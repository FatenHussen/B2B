<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('idempotency_keys')) {
            return;
        }

        Schema::table('idempotency_keys', function (Blueprint $table) {
            if (! Schema::hasColumn('idempotency_keys', 'status')) {
                $table->string('status', 16)->default('complete')->index();
            }
            if (! Schema::hasColumn('idempotency_keys', 'completed_at')) {
                $table->timestamp('completed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        //
    }
};
