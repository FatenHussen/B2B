<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('endpoint');
            $table->string('request_hash', 64)->nullable();
            $table->longText('response_body')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('status', 16)->default('processing')->index();
            $table->timestamp('created_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
