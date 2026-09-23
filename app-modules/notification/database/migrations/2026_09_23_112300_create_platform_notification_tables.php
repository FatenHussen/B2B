<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('targeting')->nullable();
            $table->json('channels')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status', 32)->default('draft');
            $table->json('stats')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('title');
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_notification_templates');
        Schema::dropIfExists('platform_campaigns');
    }
};
