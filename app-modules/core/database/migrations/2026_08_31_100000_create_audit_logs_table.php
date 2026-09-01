<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('actor_type', 64)->nullable();
            $table->string('action', 64);
            $table->string('subject_type', 128)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['subject_type', 'subject_id']);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::unprepared('
                CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT = "audit_logs is append-only";
                END
            ');
            DB::unprepared('
                CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT = "audit_logs is append-only";
                END
            ');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_delete');
        }

        Schema::dropIfExists('audit_logs');
    }
};
