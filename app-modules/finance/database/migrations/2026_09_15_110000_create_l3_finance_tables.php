<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->bigInteger('paid_total')->default(0)->after('total');
            $table->bigInteger('credited_total')->default(0)->after('paid_total');
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id');
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedInteger('qty')->default(1);
            $table->bigInteger('amount');
            $table->timestamps();
            $table->index(['supply_channel_id', 'invoice_id']);
        });

        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id');
            $table->unsignedBigInteger('invoice_id');
            $table->string('no', 32)->unique();
            $table->bigInteger('total');
            $table->string('reason');
            $table->timestamps();
            $table->index(['supply_channel_id', 'invoice_id']);
        });

        Schema::create('credit_note_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('credit_note_id');
            $table->unsignedBigInteger('invoice_line_id')->nullable();
            $table->unsignedInteger('qty')->default(1);
            $table->bigInteger('amount');
            $table->timestamps();
            $table->index('credit_note_id');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id');
            $table->unsignedBigInteger('retailer_id');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->bigInteger('amount');
            $table->string('method', 16);
            $table->string('receipt_no', 32);
            $table->timestamps();
            $table->unique(['supply_channel_id', 'receipt_no']);
            $table->index(['supply_channel_id', 'retailer_id']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id');
            $table->unsignedBigInteger('invoice_id');
            $table->bigInteger('amount');
            $table->timestamps();
            $table->index('payment_id');
            $table->index('invoice_id');
        });

        Schema::create('retailer_credit_limits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id');
            $table->unsignedBigInteger('retailer_id');
            $table->bigInteger('credit_limit');
            $table->unsignedInteger('grace_days')->default(0);
            $table->string('on_exceed', 32)->default('warn');
            $table->timestamps();
            $table->unique(['supply_channel_id', 'retailer_id']);
            $table->index(['supply_channel_id', 'retailer_id']);
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id');
            $table->unsignedBigInteger('rep_id');
            $table->string('type', 16);
            $table->bigInteger('amount');
            $table->unsignedBigInteger('settlement_id')->nullable();
            $table->string('operation_no', 32)->nullable();
            $table->timestamps();
            $table->index(['supply_channel_id', 'rep_id']);
        });

        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supply_channel_id');
            $table->unsignedBigInteger('rep_id');
            $table->bigInteger('amount');
            $table->string('operation_no', 32);
            $table->string('receipt_pdf_url');
            $table->timestamps();
            $table->index(['supply_channel_id', 'rep_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('retailer_credit_limits');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('credit_note_lines');
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('invoice_lines');
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['paid_total', 'credited_total']);
        });
    }
};
