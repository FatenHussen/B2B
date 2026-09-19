<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BE-C13. A stored response belongs to the caller who created it.
 *
 * `idempotency_keys.key` was unique on its own and `user_id` was written but never read,
 * so whoever presented a key next — another user, or nobody at all — was handed the
 * response the platform had computed for someone else. The row is now keyed by
 * (guard, user_id, key) together, and a row stuck in `processing` carries the moment its
 * lock lapses instead of blocking every retry for the full day.
 */
return new class extends Migration
{
    public function up(): void
    {
        // This table is a 24-hour memory, not a record: a row exists so that a retry of
        // the same write within a day gets the same answer, and nothing reads it after
        // that. Every row here was written while the middleware still ran ahead of
        // authentication, so none carries the guard it is about to be looked up by, and
        // guessing one would be exactly the attribution error this migration exists to
        // end. They are dropped. A client that replays a key from before this deploy
        // runs its request once more — what it would have done a day later anyway.
        DB::table('idempotency_keys')->delete();

        Schema::table('idempotency_keys', function (Blueprint $table): void {
            $table->dropUnique(['key']);

            // Both NOT NULL on purpose. MySQL treats NULLs as distinct in a unique index,
            // so a nullable guard or user would let two anonymous requests with one key
            // both insert — losing the very race the index exists to settle. A caller no
            // guard authenticated is stored as the pair IdempotencyKey::ANONYMOUS_*.
            $table->string('guard', 16)->after('key');
            $table->unsignedBigInteger('user_id')->change();

            // When the worker holding a `processing` row is presumed dead. Null once the
            // row is complete: a finished row holds no lock.
            $table->timestamp('locked_until')->nullable()->after('status');

            $table->unique(['guard', 'user_id', 'key']);
        });
    }

    public function down(): void
    {
        DB::table('idempotency_keys')->delete();

        Schema::table('idempotency_keys', function (Blueprint $table): void {
            $table->dropUnique(['guard', 'user_id', 'key']);
            $table->dropColumn(['guard', 'locked_until']);
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->unique('key');
        });
    }
};
