<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('rep_id')->nullable()->after('retailer_id');
            $table->string('source', 16)->default('office')->after('method');
            $table->timestamp('paid_at')->nullable()->after('source');
            $table->string('client_op_id', 80)->nullable()->after('paid_at');
            $table->unique(['rep_id', 'client_op_id']);
            $table->index(['supply_channel_id', 'rep_id']);
        });

        Schema::create('receipt_reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id');
            $table->string('receipt_no', 32);
            $table->unsignedBigInteger('rep_id')->index();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->timestamps();
            $table->unique(['supply_channel_id', 'receipt_no']);
            $table->index(['supply_channel_id', 'rep_id']);
        });

        Schema::table('settlements', function (Blueprint $table) {
            $table->timestamp('operated_at')->nullable()->after('operation_no');
            $table->unique(['supply_channel_id', 'operation_no']);
        });
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropUnique(['supply_channel_id', 'operation_no']);
            $table->dropColumn('operated_at');
        });
        Schema::dropIfExists('receipt_reservations');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['rep_id', 'client_op_id']);
            $table->dropIndex(['supply_channel_id', 'rep_id']);
            $table->dropColumn(['rep_id', 'source', 'paid_at', 'client_op_id']);
        });
    }
};
