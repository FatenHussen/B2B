<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BE-T04: the lightest plan table that makes `plan_id` a real reference.
 *
 * Two seeded rows, `starter` and `growth`, and the default limits each carries. No
 * pricing, no billing-cycle logic, no invoices — that is BE4-BIL01. This exists so
 * that `exists:channel_plans,id` means something and an arbitrary number does not pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_plans', function (Blueprint $table) {
            $table->id();
            $table->string('key', 32)->unique();
            $table->string('name', 96);
            // {users, warehouses, reps, skus, storage_mb} — whole numbers, rule 7.
            $table->json('limits');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_plans');
    }
};
