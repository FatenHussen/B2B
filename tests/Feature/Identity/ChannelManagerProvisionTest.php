<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Contracts\OtpChannel;
use Modules\Identity\Domain\Models\ChannelManagerInvite;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Identity\Domain\ValueObjects\PhoneNumber;
use Modules\Identity\Infrastructure\Otp\FakeOtpChannel;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\Zone;
use Modules\Tenancy\Application\Actions\RunProvisioning;
use Modules\Tenancy\Application\Jobs\ProvisionChannel;
use Modules\Tenancy\Database\Seeders\ChannelPlanSeeder;
use Modules\Tenancy\Domain\Models\ChannelPlan;
use Tests\Support\CatalogAssert;

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);
    $this->seed(ChannelPlanSeeder::class);
});

it('provisions a channel manager who can then verify OTP', function () {
    Queue::fake([ProvisionChannel::class]);

    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    $governorate = Governorate::factory()->create();
    $zone = Zone::factory()->create(['governorate_id' => $governorate->id]);
    $activityTypeId = (int) DB::table('activity_types')->insertGetId([
        'name' => 'بقالة', 'order' => 0, 'status' => RefStatus::Active->value,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $phone = '+963944'.fake()->unique()->numerify('######');

    $created = $this->postJson('/api/v1/platform/channels', [
        'name' => 'شركة الشام',
        'slug' => 'al-sham-'.uniqid(),
        'legal_form' => 'llc',
        'cr_number' => 'C12345',
        'documents' => [],
        'governorate_ids' => [$governorate->id],
        'zone_ids' => [$zone->id],
        'activity_type_ids' => [$activityTypeId],
        'logo' => null,
        'internal_note' => null,
        'plan_id' => ChannelPlan::query()->where('key', 'growth')->value('id'),
        'billing_cycle' => 'yearly',
        'trial_days' => 14,
        'limits' => [
            'users' => 25, 'warehouses' => 2, 'reps' => 20, 'skus' => 5000, 'storage_mb' => 2048,
        ],
        'custom_discount' => 0,
        'manager' => [
            'name' => 'محمد علي',
            'phone' => $phone,
            'email' => 'manager@example.sy',
            'invite_via' => 'whatsapp',
        ],
    ])->assertCreated();

    app(RunProvisioning::class)((string) $created->json('data.provisioning_job_id'));

    $user = ChannelUser::query()->where('phone', $phone)->first();
    expect($user)->not->toBeNull()
        ->and($user?->hasRole('channel_manager'))->toBeTrue()
        ->and($user?->channelMemberships())->not->toBeEmpty()
        ->and(ChannelManagerInvite::query()->where('channel_id', $created->json('data.id'))->whereNull('revoked_at')->count())->toBe(1);

    $otp = $this->postJson('/api/v1/channel/auth/request-otp', [
        'phone' => $phone,
    ]);
    CatalogAssert::ok($otp);

    /** @var FakeOtpChannel $fake */
    $fake = app(OtpChannel::class);
    $code = $fake->codeFor(PhoneNumber::normalize($phone));

    // Drop the platform Sanctum actor so channel OTP verify is unauthenticated.
    Sanctum::actingAs($admin, [], 'platform');
    $this->app['auth']->forgetGuards();

    $verify = $this->postJson('/api/v1/channel/auth/verify-otp', [
        'otp_id' => $otp->json('data.otp_id'),
        'code' => $code,
    ]);
    CatalogAssert::ok($verify);
    expect($verify->json('data.token'))->not->toBeEmpty()
        ->and($verify->json('data.channels.0.id'))->toBe($created->json('data.id'));
});

it('resets the channel manager invite and revokes sessions', function () {
    Queue::fake([ProvisionChannel::class]);

    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    $governorate = Governorate::factory()->create();
    $zone = Zone::factory()->create(['governorate_id' => $governorate->id]);
    $activityTypeId = (int) DB::table('activity_types')->insertGetId([
        'name' => 'بقالة', 'order' => 0, 'status' => RefStatus::Active->value,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $phone = '+963955'.fake()->unique()->numerify('######');

    $created = $this->postJson('/api/v1/platform/channels', [
        'name' => 'شركة حلب',
        'slug' => 'aleppo-'.uniqid(),
        'legal_form' => 'llc',
        'cr_number' => 'C99999',
        'documents' => [],
        'governorate_ids' => [$governorate->id],
        'zone_ids' => [$zone->id],
        'activity_type_ids' => [$activityTypeId],
        'logo' => null,
        'plan_id' => ChannelPlan::query()->where('key', 'growth')->value('id'),
        'billing_cycle' => 'monthly',
        'trial_days' => 0,
        'limits' => [
            'users' => 10, 'warehouses' => 1, 'reps' => 5, 'skus' => 1000, 'storage_mb' => 512,
        ],
        'custom_discount' => 0,
        'manager' => [
            'name' => 'سامر',
            'phone' => $phone,
            'email' => null,
            'invite_via' => 'whatsapp',
        ],
    ])->assertCreated();

    $channelId = (int) $created->json('data.id');
    app(RunProvisioning::class)((string) $created->json('data.provisioning_job_id'));

    $manager = ChannelUser::query()->where('phone', $phone)->firstOrFail();
    $manager->createToken('channel')->plainTextToken;
    expect($manager->tokens()->count())->toBe(1);

    $reset = $this->postJson("/api/v1/platform/channels/{$channelId}/manager/reset", [
        'reason' => 'فقد الهاتف — تذكرة SUP-880',
        'invite_via' => 'whatsapp',
    ]);
    CatalogAssert::ok($reset);
    expect($reset->json('data.invite_id'))->toBeInt()
        ->and($reset->json('data.expires_at'))->not->toBeEmpty()
        ->and($manager->fresh()->tokens()->count())->toBe(0);
});
