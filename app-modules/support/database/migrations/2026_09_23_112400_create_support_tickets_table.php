<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->text('body')->nullable();
            $table->string('status', 32)->default('open');
            $table->string('priority', 16)->default('normal');
            $table->string('requester_type', 64)->nullable();
            $table->unsignedBigInteger('requester_id')->nullable();
            $table->string('resolution')->nullable();
            $table->string('root_cause')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
