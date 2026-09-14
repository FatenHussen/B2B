<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BE-T01: every channel status transition, with who did it, why and when (rule 8).
 *
 * An append-only log. Rows are written by `ChannelLifecycle` and never updated, so there
 * is no `updated_at`; `at` is the moment of the transition and the only timestamp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('reason', 255);
            $table->timestamp('at');

            // The timeline of one channel, in order — EP-AD-052 reads it that way.
            $table->index(['channel_id', 'at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_events');
    }
};
