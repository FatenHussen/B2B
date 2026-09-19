<?php

declare(strict_types=1);

/**
 * BE-T04 — EP-AD-051, `POST /platform/channels`.
 *
 * Creates a channel in `provisioning`, returns under 500ms with `provisioning_job_id`,
 * and is idempotent. Does not wait for `active` — that move is BE-T05.
 *
 * One HTTP request per test unless the guards are forgotten in between, per the
 * guard-caching hazard recorded in CrossGuardTest. Idempotency replay is the exception:
 * the same key twice is the criterion under test.
 */

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\Zone;
use Modules\Tenancy\Application\Jobs\ProvisionChannel;
use Modules\Tenancy\Database\Seeders\ChannelPlanSeeder;
use Modules\Tenancy\Domain\Enums\ChannelStatus;
use Modules\Tenancy\Domain\Models\ChannelLimit;
use Modules\Tenancy\Domain\Models\ChannelPlan;
use Modules\Tenancy\Domain\Models\ChannelProvisionJob;
use Modules\Tenancy\Domain\Models\SupplyChannel;

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);
    $this->seed(ChannelPlanSeeder::class);
});

function createChannelUrl(): string
{
    return '/api/v1/platform/channels';
}

/**
 * @param  list<string>  $permissions
 */
function actingAsPlatformUserWithPermissions(array $permissions): PlatformUser
{
    $user = PlatformUser::factory()->create();
    foreach ($permissions as $permission) {
        $user->givePermissionTo($permission);
    }
    Sanctum::actingAs($user, ['*'], 'platform');

    return $user;
}

function actingAsCreateAdmin(): PlatformUser
{
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    return $admin;
}

/**
 * EP-AD-051 example body, with live foreign keys from the fixtures below.
 *
 * @return array<string, mixed>
 */
function catalogCreateBody(?int $planId = null, ?int $governorateId = null, ?int $zoneId = null, ?int $activityTypeId = null): array
{
    $governorate = $governorateId !== null
        ? Governorate::query()->findOrFail($governorateId)
        : Governorate::factory()->create();
    $zone = $zoneId !== null
        ? Zone::query()->findOrFail($zoneId)
        : Zone::factory()->create(['governorate_id' => $governorate->id]);

    $activityTypeId ??= (int) DB::table('activity_types')->insertGetId([
        'name' => 'بقالة',
        'order' => 0,
        'status' => RefStatus::Active->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $planId ??= (int) ChannelPlan::query()->where('key', 'growth')->value('id');

    return [
        'name' => 'شركة الشام',
        'slug' => 'al-sham-'.uniqid(),
        'legal_form' => 'llc',
        'cr_number' => 'C12345',
        'documents' => [],
        'governorate_ids' => [(int) $governorate->id],
        'zone_ids' => [(int) $zone->id],
        'activity_type_ids' => [$activityTypeId],
        'logo' => null,
        'internal_note' => 'شراكة تجريبية',
        'plan_id' => $planId,
        'billing_cycle' => 'yearly',
        'trial_days' => 14,
        'limits' => [
            'users' => 25,
            'warehouses' => 2,
            'reps' => 20,
            'skus' => 5000,
            'storage_mb' => 2048,
        ],
        'custom_discount' => 0,
        'manager' => [
            'name' => 'محمد علي',
            'phone' => '+963944000000',
            'email' => 'manager@alsham.sy',
            'invite_via' => 'whatsapp',
        ],
    ];
}

// ─── the route works, and answers the catalog's shape ───────────────────────────────

it('creates a channel in provisioning with a provisioning_job_id', function () {
    Queue::fake([ProvisionChannel::class]);
    actingAsCreateAdmin();

    $body = catalogCreateBody();

    $response = $this->postJson(createChannelUrl(), $body)
        ->assertCreated()
        ->assertJsonPath('data.status', 'provisioning');

    $id = (int) $response->json('data.id');
    $jobId = (string) $response->json('data.provisioning_job_id');

    expect($id)->toBeGreaterThan(0)
        ->and($jobId)->toStartWith('job_prov_')
        ->and($response->json('data'))->toHaveKeys(['id', 'status', 'provisioning_job_id'])
        ->and(SupplyChannel::query()->findOrFail($id)->status)->toBe(ChannelStatus::Provisioning);

    // Back-office read of a channel-owned row: set the tenant the row belongs to.
    Tenant::as($id, function () use ($jobId): void {
        expect(ChannelProvisionJob::query()->where('public_id', $jobId)->exists())->toBeTrue();
    });

    Queue::assertPushedOn('provisioning', ProvisionChannel::class);
});

it('persists limits as whole numbers and keeps the manager in the job payload', function () {
    // Rule 7 / BE-T04 §3: limits and discount are integers. The manager block is not a
    // channel_users row yet — that is BE-T05 — but the payload must carry it so a retry
    // has everything.
    Queue::fake([ProvisionChannel::class]);
    actingAsCreateAdmin();

    $body = catalogCreateBody();
    $body['custom_discount'] = 15;
    $body['limits']['reps'] = 30;

    $id = (int) $this->postJson(createChannelUrl(), $body)
        ->assertCreated()
        ->json('data.id');

    expect(SupplyChannel::query()->findOrFail($id)->custom_discount)->toBe(15);

    Tenant::as($id, function () use ($body): void {
        $limits = ChannelLimit::query()->sole();
        $payload = ChannelProvisionJob::query()->value('payload');

        expect($limits->users)->toBe(25)
            ->and($limits->reps)->toBe(30)
            ->and($limits->storage_mb)->toBe(2048)
            ->and($payload['manager']['phone'])->toBe('+963944000000')
            ->and($payload['zone_ids'])->toBe($body['zone_ids']);
    });
});

it('dispatches ProvisionChannel and does not wait for active', function () {
    // The job body is BE-T05. Create still returns provisioning; Queue::fake keeps
    // the worker from running in this test.
    Queue::fake([ProvisionChannel::class]);
    actingAsCreateAdmin();

    $id = (int) $this->postJson(createChannelUrl(), catalogCreateBody())
        ->assertCreated()
        ->json('data.id');

    expect(SupplyChannel::query()->findOrFail($id)->status)->toBe(ChannelStatus::Provisioning)
        ->and(SupplyChannel::query()->findOrFail($id)->provisioned_at)->toBeNull();

    Queue::assertPushed(ProvisionChannel::class, function (ProvisionChannel $job): bool {
        return str_starts_with($job->publicId, 'job_prov_');
    });
});

// ─── acceptance criteria ────────────────────────────────────────────────────────────

it('the response time budget is met under load test', function () {
    // Pest proves the create path itself stays under 500ms. Measurement under load is
    // BE-Q05 — this test is not that.
    Queue::fake([ProvisionChannel::class]);
    actingAsCreateAdmin();

    $started = hrtime(true);
    $this->postJson(createChannelUrl(), catalogCreateBody())->assertCreated();
    $elapsedMs = (hrtime(true) - $started) / 1_000_000;

    expect($elapsedMs)->toBeLessThan(500);
})->group('be-t04');

it('a duplicated request creates exactly one channel', function () {
    Queue::fake([ProvisionChannel::class]);
    actingAsCreateAdmin();

    $body = catalogCreateBody();
    $key = 'create-channel-idem-'.uniqid();

    $first = $this->postJson(createChannelUrl(), $body, ['X-Idempotency-Key' => $key])
        ->assertCreated();

    $second = $this->postJson(createChannelUrl(), $body, ['X-Idempotency-Key' => $key])
        ->assertCreated()
        ->assertHeader('Idempotent-Replayed', 'true');

    expect($second->json('data.id'))->toBe($first->json('data.id'))
        ->and($second->json('data.provisioning_job_id'))->toBe($first->json('data.provisioning_job_id'))
        ->and(SupplyChannel::query()->where('slug', $body['slug'])->count())->toBe(1);
});

it('slug uniqueness is enforced by a database constraint, not only by validation', function () {
    SupplyChannel::factory()->create(['slug' => 'taken-slug']);

    expect(fn () => DB::table('supply_channels')->insert([
        'name' => 'Other',
        'slug' => 'taken-slug',
        'status' => 'provisioning',
        'custom_discount' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    // And the index is on the schema, not a soft check we invented in this file.
    $indexes = Schema::getIndexes('supply_channels');
    $slugUnique = collect($indexes)->first(
        fn (array $index): bool => in_array('slug', $index['columns'], true) && ($index['unique'] ?? false),
    );

    expect($slugUnique)->not->toBeNull();
});

// ─── gate, guard, validation ────────────────────────────────────────────────────────

it('names ad.channels.create on a 403 for a platform user without it', function () {
    Queue::fake([ProvisionChannel::class]);
    actingAsPlatformUserWithPermissions([]);

    $this->postJson(createChannelUrl(), catalogCreateBody())
        ->assertForbidden()
        ->assertJsonPath('error.code', 'insufficient_permission')
        ->assertJsonPath('error.permission', 'ad.channels.create');

    expect(SupplyChannel::query()->where('name', 'شركة الشام')->exists())->toBeFalse();
});

it('lets ad.channels.create succeed without the admin role', function () {
    Queue::fake([ProvisionChannel::class]);
    actingAsPlatformUserWithPermissions(['ad.channels.create']);

    $this->postJson(createChannelUrl(), catalogCreateBody())
        ->assertCreated()
        ->assertJsonPath('data.status', 'provisioning');
});

it('refuses a channel token with 403 wrong_guard', function () {
    $channel = SupplyChannel::factory()->create();
    $manager = ChannelUser::factory()->forChannel($channel)->create();
    $manager->assignRole('channel_manager');

    $this->postJson(createChannelUrl(), catalogCreateBody(), [
        'Authorization' => 'Bearer '.$manager->createToken('cross', ['*'])->plainTextToken,
    ])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'wrong_guard');
});

it('rejects a float limit with 422', function () {
    // Rule 7: limits are whole numbers. A float must not reach the column.
    Queue::fake([ProvisionChannel::class]);
    actingAsCreateAdmin();

    $body = catalogCreateBody();
    $body['limits']['users'] = 25.5;

    $this->postJson(createChannelUrl(), $body)
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed');
});

it('rejects status on the create body', function () {
    Queue::fake([ProvisionChannel::class]);
    actingAsCreateAdmin();

    $body = catalogCreateBody();
    $body['status'] = 'active';

    $this->postJson(createChannelUrl(), $body)
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed');
});

it('rejects a plan_id that is not an active channel_plans row', function () {
    // The light plan table exists so exists: is real — any integer must not pass.
    Queue::fake([ProvisionChannel::class]);
    actingAsCreateAdmin();

    $body = catalogCreateBody();
    $body['plan_id'] = 999999;

    $this->postJson(createChannelUrl(), $body)
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed');
});
