<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_manager_invites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id')->index();
            $table->string('token_hash', 64)->unique();
            $table->string('invite_via', 32)->default('whatsapp');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_manager_invites');
    }
};
