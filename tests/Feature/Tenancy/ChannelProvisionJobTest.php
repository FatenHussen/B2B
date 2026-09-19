<?php

declare(strict_types=1);

/**
 * BE-T05 — ProvisionChannel and EP-AD-053 retry-provisioning.
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Contracts\ChannelDirectory;
use Modules\Core\Domain\Events\ChannelManagerInvited;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\Zone;
use Modules\Tenancy\Application\Actions\RunProvisioning;
use Modules\Tenancy\Application\Jobs\ProvisionChannel;
use Modules\Tenancy\Database\Seeders\ChannelPlanSeeder;
use Modules\Tenancy\Domain\Enums\ChannelStatus;
use Modules\Tenancy\Domain\Models\ChannelPlan;
use Modules\Tenancy\Domain\Models\ChannelProvisionJob;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Modules\Tenancy\Domain\Models\Warehouse;

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);
    $this->seed(ChannelPlanSeeder::class);
});

/**
 * @return array{id: int, job_id: string, body: array<string, mixed>}
 */
function createProvisioningChannel(): array
{
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

    $body = [
        'name' => 'شركة الشام',
        'slug' => 'al-sham-'.uniqid(),
        'legal_form' => 'llc',
        'cr_number' => 'C12345',
        'documents' => [],
        'governorate_ids' => [$governorate->id],
        'zone_ids' => [$zone->id],
        'activity_type_ids' => [$activityTypeId],
        'logo' => null,
        'internal_note' => 'شراكة تجريبية',
        'plan_id' => ChannelPlan::query()->where('key', 'growth')->value('id'),
        'billing_cycle' => 'yearly',
        'trial_days' => 14,
        'limits' => [
            'users' => 25, 'warehouses' => 2, 'reps' => 20, 'skus' => 5000, 'storage_mb' => 2048,
        ],
        'custom_discount' => 0,
        'manager' => [
            'name' => 'محمد علي',
            'phone' => '+963944000000',
            'email' => 'manager@alsham.sy',
            'invite_via' => 'whatsapp',
        ],
    ];

    $response = test()->postJson('/api/v1/platform/channels', $body)->assertCreated();

    return [
        'id' => (int) $response->json('data.id'),
        'job_id' => (string) $response->json('data.provisioning_job_id'),
        'body' => $body,
    ];
}

it('runs provisioning to active with one warehouse and one manager invite', function () {
    Event::fake([ChannelManagerInvited::class]);
    $created = createProvisioningChannel();

    app(RunProvisioning::class)($created['job_id']);

    $channel = SupplyChannel::query()->findOrFail($created['id']);
    expect($channel->status)->toBe(ChannelStatus::Active)
        ->and($channel->provisioned_at)->not->toBeNull()
        ->and(app(ChannelDirectory::class)->isActive($created['id']))->toBeTrue();

    Tenant::as($created['id'], function () use ($created): void {
        expect(Warehouse::query()->count())->toBe(1)
            ->and(ChannelProvisionJob::query()->where('public_id', $created['job_id'])->value('status'))->toBe('complete');
    });

    Event::assertDispatchedTimes(ChannelManagerInvited::class, 1);
});

it('a retry after a partial failure completes the remaining steps without duplicating the finished ones', function () {
    Event::fake([ChannelManagerInvited::class]);
    $created = createProvisioningChannel();

    Tenant::as($created['id'], function () use ($created): void {
        $job = ChannelProvisionJob::query()->where('public_id', $created['job_id'])->sole();
        $payload = $job->payload;
        unset($payload['manager']);
        $job->update(['payload' => $payload]);
    });

    app(RunProvisioning::class)($created['job_id']);

    expect(SupplyChannel::query()->findOrFail($created['id'])->status)->toBe(ChannelStatus::Provisioning);

    Tenant::as($created['id'], function () use ($created): void {
        expect(Warehouse::query()->count())->toBe(1)
            ->and(ChannelProvisionJob::query()->where('public_id', $created['job_id'])->value('status'))->toBe('failed')
            ->and(ChannelProvisionJob::query()->where('public_id', $created['job_id'])->value('completed_steps'))
            ->toContain(RunProvisioning::STEP_WAREHOUSE)
            ->and(ChannelProvisionJob::query()->where('public_id', $created['job_id'])->value('completed_steps'))
            ->not->toContain(RunProvisioning::STEP_INVITE);

        $job = ChannelProvisionJob::query()->where('public_id', $created['job_id'])->sole();
        $payload = $job->payload;
        $payload['manager'] = $created['body']['manager'];
        $job->update(['payload' => $payload]);
    });

    Event::assertNotDispatched(ChannelManagerInvited::class);

    $retryId = (string) $this->postJson("/api/v1/platform/channels/{$created['id']}/retry-provisioning")
        ->assertOk()
        ->json('data.job_id');

    expect($retryId)->toStartWith('job_prov_')
        ->and($retryId)->not->toBe($created['job_id']);

    app(RunProvisioning::class)($retryId);

    expect(SupplyChannel::query()->findOrFail($created['id'])->status)->toBe(ChannelStatus::Active);

    Tenant::as($created['id'], function (): void {
        expect(Warehouse::query()->count())->toBe(1);
    });

    Event::assertDispatchedTimes(ChannelManagerInvited::class, 1);
});

it('a failed provisioning never leaves a half-usable channel that can accept orders', function () {
    $created = createProvisioningChannel();

    Tenant::as($created['id'], function () use ($created): void {
        $job = ChannelProvisionJob::query()->where('public_id', $created['job_id'])->sole();
        $payload = $job->payload;
        unset($payload['manager']);
        $job->update(['payload' => $payload]);
    });

    app(RunProvisioning::class)($created['job_id']);

    expect(SupplyChannel::query()->findOrFail($created['id'])->status)->toBe(ChannelStatus::Provisioning)
        ->and(app(ChannelDirectory::class)->isActive($created['id']))->toBeFalse();
});

it('returns the same job_id when retrying an already active channel', function () {
    $created = createProvisioningChannel();
    app(RunProvisioning::class)($created['job_id']);

    $this->postJson("/api/v1/platform/channels/{$created['id']}/retry-provisioning")
        ->assertOk()
        ->assertJsonPath('data.job_id', $created['job_id']);

    Tenant::as($created['id'], function (): void {
        expect(Warehouse::query()->count())->toBe(1)
            ->and(ChannelProvisionJob::query()->count())->toBe(1);
    });
});

it('names ad.channels.update on a 403 for a platform user without it', function () {
    $created = createProvisioningChannel();

    $user = PlatformUser::factory()->create();
    Sanctum::actingAs($user, ['*'], 'platform');

    $this->postJson("/api/v1/platform/channels/{$created['id']}/retry-provisioning")
        ->assertForbidden()
        ->assertJsonPath('error.code', 'insufficient_permission')
        ->assertJsonPath('error.permission', 'ad.channels.update');
});
