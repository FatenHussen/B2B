<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32); // terms|privacy
            $table->string('version', 32);
            $table->longText('body_ar');
            $table->date('effective_from');
            $table->boolean('requires_reconsent')->default(false);
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();
            $table->unique(['type', 'version']);
        });

        Schema::create('help_guides', function (Blueprint $table) {
            $table->id();
            $table->string('audience', 32);
            $table->string('title');
            $table->longText('body');
            $table->string('status', 32)->default('draft');
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
            $table->index(['audience', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_guides');
        Schema::dropIfExists('legal_documents');
    }
};
