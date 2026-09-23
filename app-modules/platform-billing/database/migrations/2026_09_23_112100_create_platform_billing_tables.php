<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id')->index();
            $table->unsignedBigInteger('plan_id');
            $table->string('cycle', 16)->default('monthly');
            $table->string('status', 32)->default('active');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('next_renewal_at')->nullable();
            $table->unsignedBigInteger('amount')->default(0);
            $table->unsignedBigInteger('scheduled_plan_id')->nullable();
            $table->timestamp('effective_from')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('no', 32)->unique();
            $table->unsignedBigInteger('channel_id')->index();
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->unsignedBigInteger('amount')->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->string('status', 32)->default('open');
            $table->string('pdf_path')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_credit_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('platform_invoice_id');
            $table->unsignedBigInteger('amount')->default(0);
            $table->string('reason', 500);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::table('supply_channels', function (Blueprint $table) {
            if (! Schema::hasColumn('supply_channels', 'subscription_status')) {
                $table->string('subscription_status', 32)->nullable()->after('plan_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('supply_channels', function (Blueprint $table) {
            if (Schema::hasColumn('supply_channels', 'subscription_status')) {
                $table->dropColumn('subscription_status');
            }
        });
        Schema::dropIfExists('platform_credit_notes');
        Schema::dropIfExists('platform_invoices');
        Schema::dropIfExists('channel_subscriptions');
    }
};
