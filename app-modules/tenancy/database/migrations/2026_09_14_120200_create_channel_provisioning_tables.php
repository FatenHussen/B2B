<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BE-T04: what a channel is created with, in the tables that keep it.
 *
 * Every table here is channel-owned and carries `channel_id` first in its index
 * (rule 10); the models apply BelongsToChannel. The create action inserts with the
 * channel id set explicitly, which needs no tenant; later reads from the back office
 * run under `Tenant::as($channelId)`.
 *
 * `channel_provision_jobs` is the handle EP-AD-051 returns as `provisioning_job_id`
 * and EP-AD-053 retries. It holds the whole request payload the job will materialise
 * (BE-T05) — the manager block, the zones, the limits — so a retry has everything and
 * needs nothing from the request that started it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_limits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id')->unique();
            // Whole numbers only (rule 7; BE-T04 §3).
            $table->unsignedInteger('users');
            $table->unsignedInteger('warehouses');
            $table->unsignedInteger('reps');
            $table->unsignedInteger('skus');
            $table->unsignedInteger('storage_mb');
            // EP-AD-055 (BE-T12): an override that reverts on its own.
            $table->timestamp('temporary_until')->nullable();
            $table->string('reason', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('channel_governorates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');
            $table->unsignedBigInteger('governorate_id');
            $table->timestamps();
            $table->unique(['channel_id', 'governorate_id']);
        });

        Schema::create('channel_activity_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');
            $table->unsignedBigInteger('activity_type_id');
            $table->timestamps();
            $table->unique(['channel_id', 'activity_type_id']);
        });

        Schema::create('channel_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');
            // A media id, stored as given: there is no media module to validate it
            // against yet (BE-X03).
            $table->unsignedBigInteger('media_id');
            $table->timestamps();
            $table->index(['channel_id', 'media_id']);
        });

        Schema::create('channel_internal_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');
            $table->text('body');
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('created_at');
            $table->index(['channel_id', 'created_at']);
        });

        Schema::create('channel_provision_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->unsignedBigInteger('channel_id');
            $table->string('status', 16)->default('queued');
            $table->json('payload');
            $table->text('error')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['channel_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_provision_jobs');
        Schema::dropIfExists('channel_internal_notes');
        Schema::dropIfExists('channel_documents');
        Schema::dropIfExists('channel_activity_types');
        Schema::dropIfExists('channel_governorates');
        Schema::dropIfExists('channel_limits');
    }
};
