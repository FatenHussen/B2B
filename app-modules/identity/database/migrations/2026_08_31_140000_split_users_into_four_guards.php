<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 32)->nullable()->unique();
            $table->string('password');
            $table->string('status', 32)->default('active')->index();
            $table->text('two_factor_secret')->nullable();
            $table->text('pending_two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('channel_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 32)->unique();
            $table->string('email')->nullable()->unique();
            $table->string('status', 32)->default('active')->index();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('warehouse_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status', 32)->default('active')->index();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('app_users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('');
            $table->string('phone', 32)->unique();
            $table->string('kind', 16)->nullable()->index();
            $table->string('status', 32)->default('pending')->index();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('channel_user_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_user_id')->constrained('channel_users')->cascadeOnDelete();
            $table->unsignedBigInteger('channel_id')->index();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['channel_user_id', 'channel_id']);
        });

        Schema::create('otp_challenges', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 32)->unique();
            $table->foreignId('platform_user_id')->constrained('platform_users')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();
        });

        Schema::create('app_devices', function (Blueprint $table) {
            $table->id();
            $table->string('tokenable_type');
            $table->unsignedBigInteger('tokenable_id');
            $table->string('device_uuid');
            $table->string('platform', 32)->nullable();
            $table->string('name')->nullable();
            $table->string('push_token')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->index(['tokenable_type', 'tokenable_id']);
            $table->unique(['tokenable_type', 'tokenable_id', 'device_uuid']);
        });

        Schema::create('warehouse_devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_token_hash', 64)->unique();
            $table->string('pin_hash');
            $table->unsignedBigInteger('warehouse_id')->index();
            $table->unsignedBigInteger('channel_id')->index();
            $table->foreignId('warehouse_user_id')->nullable()->constrained('warehouse_users')->nullOnDelete();
            $table->string('label')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('retailer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_user_id')->unique()->constrained('app_users')->cascadeOnDelete();
            $table->string('shop_name');
            $table->unsignedBigInteger('activity_type_id');
            $table->unsignedBigInteger('governorate_id');
            $table->unsignedBigInteger('zone_id');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('address')->nullable();
            $table->string('status', 32)->default('pending_review')->index();
            $table->timestamps();
        });

        Schema::create('rep_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_user_id')->unique()->constrained('app_users')->cascadeOnDelete();
            $table->unsignedBigInteger('channel_id')->index();
            $table->unsignedBigInteger('activity_type_id');
            $table->string('status', 32)->default('pending_review')->index();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('rep_profile_zones', function (Blueprint $table) {
            $table->foreignId('rep_profile_id')->constrained('rep_profiles')->cascadeOnDelete();
            $table->unsignedBigInteger('zone_id');
            $table->primary(['rep_profile_id', 'zone_id']);
        });

        Schema::create('retailer_profile_categories', function (Blueprint $table) {
            $table->foreignId('retailer_profile_id')->constrained('retailer_profiles')->cascadeOnDelete();
            $table->unsignedBigInteger('root_category_id');
            $table->primary(['retailer_profile_id', 'root_category_id'], 'retailer_profile_categories_pk');
        });

        Schema::create('retailer_profile_equipments', function (Blueprint $table) {
            $table->foreignId('retailer_profile_id')->constrained('retailer_profiles')->cascadeOnDelete();
            $table->unsignedBigInteger('equipment_id');
            $table->primary(['retailer_profile_id', 'equipment_id'], 'retailer_profile_equipments_pk');
        });

        Schema::create('platform_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_user_id')->constrained('platform_users')->cascadeOnDelete();
            $table->string('token_id')->nullable()->index();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();
        });

        Schema::create('password_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_user_id')->unique()->constrained('platform_users')->cascadeOnDelete();
            $table->timestamp('confirmed_until');
            $table->timestamps();
        });

        Schema::table('otp_requests', function (Blueprint $table) {
            $table->string('public_id', 32)->nullable()->after('id');
            $table->string('channel_used', 16)->default('whatsapp')->after('purpose');
            $table->string('client', 64)->nullable()->after('channel_used');
            $table->string('ip', 45)->nullable()->after('client');
            $table->string('device_id', 64)->nullable()->after('ip');
        });

        foreach (DB::table('otp_requests')->orderBy('id')->get() as $row) {
            DB::table('otp_requests')->where('id', $row->id)->update([
                'public_id' => 'otp_'.substr(bin2hex(random_bytes(4)), 0, 6),
            ]);
        }

        Schema::table('otp_requests', function (Blueprint $table) {
            $table->unique('public_id');
        });

        $this->migrateLegacyUsers();

        Schema::dropIfExists('devices');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'supply_channel_id')) {
                $table->dropConstrainedForeignId('supply_channel_id');
            }
        });

        Schema::dropIfExists('users');
    }

    public function down(): void
    {
        Schema::dropIfExists('password_confirmations');
        Schema::dropIfExists('platform_sessions');
        Schema::dropIfExists('retailer_profile_equipments');
        Schema::dropIfExists('retailer_profile_categories');
        Schema::dropIfExists('rep_profile_zones');
        Schema::dropIfExists('rep_profiles');
        Schema::dropIfExists('retailer_profiles');
        Schema::dropIfExists('warehouse_devices');
        Schema::dropIfExists('app_devices');
        Schema::dropIfExists('otp_challenges');
        Schema::dropIfExists('channel_user_channels');
        Schema::dropIfExists('app_users');
        Schema::dropIfExists('warehouse_users');
        Schema::dropIfExists('channel_users');
        Schema::dropIfExists('platform_users');
    }

    private function migrateLegacyUsers(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $rows = DB::table('users')->orderBy('id')->get();

        foreach ($rows as $row) {
            match ($row->type ?? 'channel_user') {
                'admin' => $this->intoPlatform($row),
                'channel_user' => $this->intoChannel($row),
                'warehouse' => $this->intoWarehouse($row),
                'retailer', 'rep' => $this->intoApp($row),
                default => $this->intoChannel($row),
            };
        }
    }

    private function intoPlatform(object $row): void
    {
        $email = $row->email ?: 'migrated-'.$row->id.'@platform.local';

        DB::table('platform_users')->insert([
            'id' => $row->id,
            'name' => $row->name,
            'email' => $email,
            'phone' => $row->phone ?: null,
            'password' => $row->password ?: bcrypt('password'),
            'status' => $row->status ?? 'active',
            'last_login_at' => $row->last_login_at,
            'remember_token' => $row->remember_token ?? null,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ]);
    }

    private function intoChannel(object $row): void
    {
        $id = DB::table('channel_users')->insertGetId([
            'name' => $row->name,
            'phone' => $row->phone,
            'email' => $row->email,
            'status' => $row->status ?? 'active',
            'last_login_at' => $row->last_login_at,
            'remember_token' => $row->remember_token ?? null,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ]);

        if (! empty($row->supply_channel_id)) {
            DB::table('channel_user_channels')->insert([
                'channel_user_id' => $id,
                'channel_id' => $row->supply_channel_id,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function intoWarehouse(object $row): void
    {
        DB::table('warehouse_users')->insert([
            'name' => $row->name,
            'status' => $row->status ?? 'active',
            'last_login_at' => $row->last_login_at,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ]);
    }

    private function intoApp(object $row): void
    {
        DB::table('app_users')->insert([
            'name' => $row->name,
            'phone' => $row->phone,
            'kind' => $row->type,
            'status' => $row->status ?? 'active',
            'last_login_at' => $row->last_login_at,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ]);
    }
};
