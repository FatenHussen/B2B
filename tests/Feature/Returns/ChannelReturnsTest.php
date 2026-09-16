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

function returnsManager(SupplyChannel $channel): ChannelUser
{
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $user->assignRole('channel_manager');

    return $user;
}

function seedReturnRequest(int $channelId, string $no, string $status = 'pending', int $zoneId = 12, ?int $repId = 70): int
{
    return (int) DB::table('return_requests')->insertGetId([
        'channel_id' => $channelId,
        'sub_order_id' => 1,
        'zone_id' => $zoneId,
        'rep_id' => $repId,
        'requester_type' => ChannelUser::class,
        'requester_id' => 1,
        'type' => 'return',
        'status' => $status,
        'request_no' => $no,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('paginates and filters the channel return inbox', function () {
    $channel = SupplyChannel::factory()->create();
    Tenant::as($channel->id, function () use ($channel): void {
        seedReturnRequest($channel->id, 'RR-A', 'pending', 12, 70);
        seedReturnRequest($channel->id, 'RR-B', 'approved', 12, 70);
        seedReturnRequest($channel->id, 'RR-C', 'pending', 99, 70);
    });

    Sanctum::actingAs(returnsManager($channel), ['*'], 'channel');
    $response = $this->getJson('/api/v1/channel/return-requests?filter[status]=pending&filter[zone_id]=12');
    CatalogAssert::ok($response);

    $nos = collect($response->json('data'))->pluck('request_no')->all();
    expect($response->json('meta'))->toHaveKeys(['page', 'per_page', 'total'])
        ->and($nos)->toContain('RR-A')
        ->and($nos)->not->toContain('RR-B')
        ->and($nos)->not->toContain('RR-C')
        ->and($response->json('data.0'))->toHaveKeys(['id', 'request_no', 'type', 'status']);
});

it('hides another channel return and 404s decide on a foreign id', function () {
    $own = SupplyChannel::factory()->create();
    $foreign = SupplyChannel::factory()->create();
    $ownId = Tenant::as($own->id, fn () => seedReturnRequest($own->id, 'RR-MINE'));
    $foreignId = Tenant::as($foreign->id, fn () => seedReturnRequest($foreign->id, 'RR-THEIRS'));

    Sanctum::actingAs(returnsManager($own), ['*'], 'channel');
    $list = $this->getJson('/api/v1/channel/return-requests');
    CatalogAssert::ok($list);
    $ids = collect($list->json('data'))->pluck('id')->all();
    expect($ids)->toContain($ownId)->not->toContain($foreignId);

    CatalogAssert::error(
        $this->postJson('/api/v1/channel/return-requests/'.$foreignId.'/decide', [
            'decision' => 'approve',
        ]),
        404,
        'not_found',
    );
})->group('tenancy');

it('rejects a second decide on a non-pending return', function () {
    $channel = SupplyChannel::factory()->create();
    $id = Tenant::as($channel->id, fn () => seedReturnRequest($channel->id, 'RR-DONE', 'approved'));

    Sanctum::actingAs(returnsManager($channel), ['*'], 'channel');
    CatalogAssert::error(
        $this->postJson('/api/v1/channel/return-requests/'.$id.'/decide', [
            'decision' => 'reject',
            'reason' => 'already closed',
        ]),
        409,
        'illegal_transition',
    );
});
