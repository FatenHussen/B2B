<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sod_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('permission_a', 96);
            $table->string('permission_b', 96);
            $table->string('reason', 255);
            $table->json('exceptions')->nullable();
            $table->timestamps();
        });

        Schema::create('access_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 48);
            $table->string('status', 32)->default('pending')->index();
            $table->string('permission', 96)->nullable();
            $table->string('action', 64)->nullable();
            $table->json('payload');
            $table->unsignedBigInteger('requester_id');
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->timestamps();
        });

        Schema::create('temp_grants', function (Blueprint $table): void {
            $table->id();
            $table->string('grantee_type', 64);
            $table->unsignedBigInteger('grantee_id');
            $table->string('permission', 96);
            $table->string('status', 32)->default('pending_approval');
            $table->unsignedInteger('duration_minutes');
            $table->timestamp('granted_until')->nullable();
            $table->timestamp('expires_request_at')->nullable();
            $table->unsignedBigInteger('request_id')->nullable();
            $table->unsignedBigInteger('requester_id');
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->string('reason', 500);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['grantee_type', 'grantee_id']);
        });

        Schema::create('access_reviews', function (Blueprint $table): void {
            $table->id();
            $table->string('quarter', 16);
            $table->string('scope', 32);
            $table->string('status', 32)->default('open');
            $table->unsignedBigInteger('started_by');
            $table->timestamps();
        });

        Schema::create('access_review_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('access_review_id')->constrained('access_reviews')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->string('suggestion', 32)->default('revoke_unused');
            $table->string('decision', 16)->nullable();
            $table->string('reason', 500)->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_review_items');
        Schema::dropIfExists('access_reviews');
        Schema::dropIfExists('temp_grants');
        Schema::dropIfExists('access_change_requests');
        Schema::dropIfExists('sod_rules');
    }
};
