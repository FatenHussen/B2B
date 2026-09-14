<?php

declare(strict_types=1);

/**
 * BE-T06 — EP-AD-052 show and EP-AD-062 update.
 */

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Domain\Models\AuditLog;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Tenancy\Domain\Models\SupplyChannel;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function detailActingAsAdmin(): PlatformUser
{
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    return $admin;
}

it('a brand-new channel returns genuine zeros, not placeholder values', function () {
    // Catalog example is gmv_30d 1850000000 / orders_30d 3100. Those are not this
    // channel's figures. Tenancy does not read Ordering, so a channel with no orders
    // answers 0 — computed, not a copied example.
    $channel = SupplyChannel::factory()->create();
    detailActingAsAdmin();

    $this->getJson("/api/v1/admin/channels/{$channel->id}")
        ->assertOk()
        ->assertJsonPath('data.channel.id', $channel->id)
        ->assertJsonPath('data.channel.status', $channel->status->value)
        ->assertJsonPath('data.kpis.gmv_30d', 0)
        ->assertJsonPath('data.kpis.orders_30d', 0)
        ->assertJsonPath('data.manager', null)
        ->assertJsonPath('data.limits.users', 0)
        ->assertJsonPath('data.limits.skus', 0);

    $kpis = $this->getJson("/api/v1/admin/channels/{$channel->id}")->json('data.kpis');

    expect($kpis['gmv_30d'])->toBe(0)
        ->and($kpis['orders_30d'])->toBe(0)
        ->and($kpis)->not->toMatchArray(['gmv_30d' => 1_850_000_000, 'orders_30d' => 3100]);
});

it('the update is refused without a reason', function () {
    $channel = SupplyChannel::factory()->create(['name' => 'شركة النور']);
    detailActingAsAdmin();

    $this->putJson("/api/v1/admin/channels/{$channel->id}", [
        'name' => 'شركة النور للتوزيع',
        'legal_form' => 'llc',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed');

    expect($channel->fresh()->name)->toBe('شركة النور');
});

it('updates the profile when a reason is given and writes an audit row', function () {
    $channel = SupplyChannel::factory()->create(['name' => 'شركة النور']);
    $admin = detailActingAsAdmin();

    $this->putJson("/api/v1/admin/channels/{$channel->id}", [
        'name' => 'شركة النور للتوزيع',
        'legal_form' => 'llc',
        'cr_number' => 'C12345',
        'internal_note' => 'تحديث السجل',
        'reason' => 'تصحيح الاسم التجاري',
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $channel->id);

    expect($channel->fresh()->name)->toBe('شركة النور للتوزيع')
        ->and($channel->fresh()->legal_form)->toBe('llc');

    $audit = AuditLog::query()->where('action', 'channel.update')->where('subject_id', $channel->id)->sole();
    expect($audit->properties['reason'] ?? null)->toBe('تصحيح الاسم التجاري')
        ->and((int) $audit->actor_id)->toBe($admin->id);
});

it('names ad.channels.view on a 403 for a platform user without it', function () {
    $channel = SupplyChannel::factory()->create();
    $user = PlatformUser::factory()->create();
    Sanctum::actingAs($user, ['*'], 'platform');

    $this->getJson("/api/v1/admin/channels/{$channel->id}")
        ->assertForbidden()
        ->assertJsonPath('error.code', 'insufficient_permission')
        ->assertJsonPath('error.permission', 'ad.channels.view');
});
