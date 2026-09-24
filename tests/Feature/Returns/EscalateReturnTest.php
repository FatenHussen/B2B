<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function escalateManager(SupplyChannel $channel): ChannelUser
{
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $user->assignRole('channel_manager');

    return $user;
}

/**
 * @param  array{status?: string, sla_due_at?: mixed, escalated_at?: mixed}  $extra
 */
function seedEscalatableReturn(int $channelId, string $no, array $extra = []): int
{
    return (int) DB::table('return_requests')->insertGetId([
        'channel_id' => $channelId,
        'sub_order_id' => 1,
        'zone_id' => 12,
        'rep_id' => 70,
        'requester_type' => ChannelUser::class,
        'requester_id' => 1,
        'type' => 'return',
        'status' => $extra['status'] ?? 'pending',
        'request_no' => $no,
        'sla_due_at' => $extra['sla_due_at'] ?? now()->subHour(),
        'escalated_at' => $extra['escalated_at'] ?? null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('escalates an open overdue return and records audit', function () {
    $channel = SupplyChannel::factory()->create();
    $id = Tenant::as($channel->id, fn () => seedEscalatableReturn($channel->id, 'RR-ESC'));

    Sanctum::actingAs(escalateManager($channel), ['*'], 'channel');
    $response = $this->postJson('/api/v1/channel/return-requests/'.$id.'/escalate', [
        'message' => 'تجاوز SLA',
    ]);
    CatalogAssert::ok($response);

    expect($response->json('data'))->toMatchArray([
        'id' => $id,
        'overdue' => true,
        'notified' => true,
    ])->and($response->json('data.escalated_at'))->not->toBeNull();

    $row = DB::table('return_requests')->where('id', $id)->first();
    expect($row->escalated_at)->not->toBeNull();

    expect(DB::table('audit_logs')->where([
        'action' => 'returns.escalate',
        'subject_type' => 'return_request',
        'subject_id' => $id,
    ])->exists())->toBeTrue();
});

it('rejects escalate when the return is not overdue', function () {
    $channel = SupplyChannel::factory()->create();
    $id = Tenant::as($channel->id, fn () => seedEscalatableReturn($channel->id, 'RR-FRESH', [
        'sla_due_at' => now()->addHours(2),
    ]));

    Sanctum::actingAs(escalateManager($channel), ['*'], 'channel');
    CatalogAssert::error(
        $this->postJson('/api/v1/channel/return-requests/'.$id.'/escalate', [
            'message' => 'too early',
        ]),
        409,
        'illegal_transition',
    );
});

it('rejects escalate when the return is closed', function () {
    $channel = SupplyChannel::factory()->create();
    $id = Tenant::as($channel->id, fn () => seedEscalatableReturn($channel->id, 'RR-CLOSED', [
        'status' => 'rejected',
        'sla_due_at' => now()->subHour(),
    ]));

    Sanctum::actingAs(escalateManager($channel), ['*'], 'channel');
    CatalogAssert::error(
        $this->postJson('/api/v1/channel/return-requests/'.$id.'/escalate'),
        409,
        'illegal_transition',
    );
});

it('404s escalate on a foreign channel return', function () {
    $own = SupplyChannel::factory()->create();
    $foreign = SupplyChannel::factory()->create();
    $foreignId = Tenant::as($foreign->id, fn () => seedEscalatableReturn($foreign->id, 'RR-THEIRS'));

    Sanctum::actingAs(escalateManager($own), ['*'], 'channel');
    CatalogAssert::error(
        $this->postJson('/api/v1/channel/return-requests/'.$foreignId.'/escalate'),
        404,
        'not_found',
    );
})->group('tenancy');
