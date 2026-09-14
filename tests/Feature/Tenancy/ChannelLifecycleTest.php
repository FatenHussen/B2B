<?php

declare(strict_types=1);

/**
 * BE-T01 — the channel state machine, and the one service allowed to move a channel
 * through it.
 *
 * The matrix under test is the one three sources agree on: EP-AD-054 in the catalog
 * (`suspended` → `[active, archived]`), the sprint spec §5.1, and the tickets that name
 * each edge (BE-T05 §2, BE-T13 §2, BE-T15 §1, BE-T01 §2). Sixteen ordered pairs exist
 * between four states; four are transitions and twelve are not, and every one of the
 * sixteen is asserted here by name so the matrix cannot grow or shrink silently.
 *
 * `active → archived` gets its own tests on top of that. It is the one edge a client
 * would draw by intuition — "archive" looks like a thing you do to a running channel —
 * and the reason `allowed_next` exists is so the client draws what the server permits
 * rather than what looks reasonable.
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Domain\Events\ChannelStatusChanged;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Tenancy\Application\Services\ChannelLifecycle;
use Modules\Tenancy\Domain\ChannelStateMachine;
use Modules\Tenancy\Domain\Enums\ChannelStatus;
use Modules\Tenancy\Domain\Models\ChannelEvent;
use Modules\Tenancy\Domain\Models\SupplyChannel;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

/** The four transitions that exist. */
dataset('allowed channel transitions', [
    'provisioning → active' => ['provisioning', 'active'],
    'active → suspended' => ['active', 'suspended'],
    'suspended → active' => ['suspended', 'active'],
    'suspended → archived' => ['suspended', 'archived'],
]);

/** The twelve ordered pairs that are not transitions, self-transitions included. */
dataset('forbidden channel transitions', [
    'provisioning → provisioning' => ['provisioning', 'provisioning'],
    'provisioning → suspended' => ['provisioning', 'suspended'],
    'provisioning → archived' => ['provisioning', 'archived'],
    'active → provisioning' => ['active', 'provisioning'],
    'active → active' => ['active', 'active'],
    'active → archived' => ['active', 'archived'],
    'suspended → provisioning' => ['suspended', 'provisioning'],
    'suspended → suspended' => ['suspended', 'suspended'],
    'archived → provisioning' => ['archived', 'provisioning'],
    'archived → active' => ['archived', 'active'],
    'archived → suspended' => ['archived', 'suspended'],
    'archived → archived' => ['archived', 'archived'],
]);

function channelIn(string $status): SupplyChannel
{
    // The factory writes under Model::unguarded(), which is the only way a test may put
    // a channel into a state without going through the lifecycle.
    return SupplyChannel::factory()->create(['status' => $status]);
}

function platformActor(): PlatformUser
{
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');

    return $admin;
}

// ─── the matrix, edge by edge ───────────────────────────────────────────────────────

it('allows the transition', function (string $from, string $to) {
    $channel = channelIn($from);

    $moved = app(ChannelLifecycle::class)->transition(
        $channel,
        ChannelStatus::from($to),
        platformActor(),
        'اختبار انتقال مسموح',
    );

    expect($moved->status)->toBe(ChannelStatus::from($to))
        ->and(DB::table('supply_channels')->where('id', $channel->id)->value('status'))->toBe($to);
})->with('allowed channel transitions');

it('refuses the transition with 409 illegal_transition and changes nothing', function (string $from, string $to) {
    $channel = channelIn($from);

    try {
        app(ChannelLifecycle::class)->transition($channel, ChannelStatus::from($to), platformActor(), 'محاولة');
        test()->fail("{$from} → {$to} was accepted");
    } catch (DomainException $e) {
        expect($e->errorCode)->toBe('illegal_transition')
            ->and($e->status)->toBe(409);
    }

    expect(DB::table('supply_channels')->where('id', $channel->id)->value('status'))->toBe($from)
        ->and(ChannelEvent::query()->where('channel_id', $channel->id)->count())->toBe(0);
})->with('forbidden channel transitions');

it('pins the matrix to exactly the four edges the sources name', function () {
    // The two datasets above are the contract; this ties the constant to them so that an
    // edge added to MATRIX without a test — or a test without an edge — fails here.
    expect(ChannelStateMachine::MATRIX)->toBe([
        'provisioning' => ['active'],
        'active' => ['suspended'],
        'suspended' => ['active', 'archived'],
        'archived' => [],
    ]);
});

// ─── active → archived, by name ─────────────────────────────────────────────────────

it('does not let an active channel be archived', function () {
    // BE-T01 §2: "active → archived does not exist as a transition and must not be
    // reachable." The path is active → suspended → archived, and the suspension is
    // what freezes new orders before the channel is put away.
    $channel = channelIn('active');

    expect(fn () => app(ChannelLifecycle::class)->transition($channel, ChannelStatus::Archived, platformActor(), 'أرشفة مباشرة'))
        ->toThrow(DomainException::class);

    expect($channel->fresh()->status)->toBe(ChannelStatus::Active);
});

it('omits archived from allowed_next while the channel is active', function () {
    // The client renders exactly the buttons in allowed_next. If `archived` appeared here
    // the client would draw an "archive" button that answers 409 every time.
    $channel = channelIn('active');
    Sanctum::actingAs(platformActor(), ['*'], 'platform');

    $allowed = $this->getJson("/api/v1/admin/channels/{$channel->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->json('data.allowed_next');

    expect($allowed)->toBe(['suspended'])
        ->and($allowed)->not->toContain('archived');
});

// ─── allowed_next on the resource, every state ──────────────────────────────────────

it('returns allowed_next on the channel resource', function (string $status, array $expected) {
    $channel = channelIn($status);
    Sanctum::actingAs(platformActor(), ['*'], 'platform');

    $this->getJson("/api/v1/admin/channels/{$channel->id}")
        ->assertOk()
        ->assertJsonPath('data.status', $status)
        ->assertJsonPath('data.allowed_next', $expected);
})->with([
    'provisioning' => ['provisioning', ['active']],
    'active' => ['active', ['suspended']],
    'suspended' => ['suspended', ['active', 'archived']],
    'archived' => ['archived', []],
]);

// ─── every transition is recorded with actor, reason and time ───────────────────────

it('records every transition with actor, reason and time', function () {
    $channel = channelIn('active');
    $actor = platformActor();
    $before = now()->subSecond();

    app(ChannelLifecycle::class)->transition($channel, ChannelStatus::Suspended, $actor, 'تأخر سداد فاتورة المنصة');

    $event = ChannelEvent::query()->where('channel_id', $channel->id)->sole();

    expect($event->from_status)->toBe(ChannelStatus::Active)
        ->and($event->to_status)->toBe(ChannelStatus::Suspended)
        ->and($event->actor_type)->toBe(PlatformUser::class)
        ->and((int) $event->actor_id)->toBe($actor->id)
        ->and($event->reason)->toBe('تأخر سداد فاتورة المنصة')
        ->and($event->at->greaterThanOrEqualTo($before))->toBeTrue();
});

it('keeps one event per transition, in order', function () {
    $channel = channelIn('provisioning');
    $actor = platformActor();
    $lifecycle = app(ChannelLifecycle::class);

    $lifecycle->transition($channel, ChannelStatus::Active, $actor, 'اكتمل التجهيز');
    $lifecycle->transition($channel, ChannelStatus::Suspended, $actor, 'إيقاف');
    $lifecycle->transition($channel, ChannelStatus::Active, $actor, 'رفع الإيقاف');

    $trail = ChannelEvent::query()->where('channel_id', $channel->id)->orderBy('id')->get()
        ->map(fn (ChannelEvent $e) => $e->from_status->value.'→'.$e->to_status->value)
        ->all();

    expect($trail)->toBe(['provisioning→active', 'active→suspended', 'suspended→active']);
});

// ─── ChannelStatusChanged: once per transition that happened, never for one that did not ─

it('dispatches ChannelStatusChanged exactly once for the transition', function (string $from, string $to) {
    // From transition() and nowhere else — and transition() is the only writer, so
    // "once per successful call" is the same statement as "once per status change".
    Event::fake([ChannelStatusChanged::class]);
    $channel = channelIn($from);
    $actor = platformActor();

    app(ChannelLifecycle::class)->transition($channel, ChannelStatus::from($to), $actor, 'سبب الانتقال');

    Event::assertDispatchedTimes(ChannelStatusChanged::class, 1);
    Event::assertDispatched(ChannelStatusChanged::class, fn (ChannelStatusChanged $e) => $e->channelId === $channel->id
        && $e->fromStatus === $from
        && $e->toStatus === $to
        && $e->actorType === PlatformUser::class
        && $e->actorId === $actor->id
        && $e->reason === 'سبب الانتقال');
})->with('allowed channel transitions');

it('does not dispatch ChannelStatusChanged for a refused transition', function (string $from, string $to) {
    Event::fake([ChannelStatusChanged::class]);
    $channel = channelIn($from);

    expect(fn () => app(ChannelLifecycle::class)->transition($channel, ChannelStatus::from($to), platformActor(), 'محاولة'))
        ->toThrow(DomainException::class);

    Event::assertNotDispatched(ChannelStatusChanged::class);
})->with('forbidden channel transitions');

it('stamps the event with the same instant it wrote to the trail', function () {
    // One transition, one moment: a listener that reconciles against channel_events
    // must find the row the event describes, not one a second earlier.
    Event::fake([ChannelStatusChanged::class]);
    $channel = channelIn('active');

    app(ChannelLifecycle::class)->transition($channel, ChannelStatus::Suspended, platformActor(), 'إيقاف');

    $row = ChannelEvent::query()->where('channel_id', $channel->id)->sole();

    Event::assertDispatched(ChannelStatusChanged::class, fn (ChannelStatusChanged $e) => $e->at->format('Y-m-d H:i:s') === $row->at->format('Y-m-d H:i:s'));
});

// ─── rule 8: status moves through the service and nowhere else ──────────────────────

it('drops status from mass assignment on the model', function () {
    // `status` is guarded. Neither create() nor update() may set it; the lifecycle is
    // the only writer. This pins Eloquent's behaviour for a guarded key so a future
    // `$fillable` edit that re-admits it fails here rather than in production.
    $channel = SupplyChannel::create([
        'name' => 'شركة الاختبار',
        'slug' => 'test-guarded',
        'status' => 'suspended',
    ]);

    expect($channel->fresh()->status)->toBe(ChannelStatus::Active);

    $channel->update(['status' => 'archived']);

    expect($channel->fresh()->status)->toBe(ChannelStatus::Active);
});

it('rejects status on channel creation with 422', function () {
    // Until BE-T01 the admin routes accepted `status` as an ordinary field, which is the
    // exact thing rule 8 forbids. Prohibiting it is louder than silently ignoring it: a
    // client that still sends the field learns so from the response, not from a channel
    // that stayed active.
    Sanctum::actingAs(platformActor(), ['*'], 'platform');

    $this->postJson('/api/v1/admin/channels', [
        'name' => 'شركة جديدة',
        'slug' => 'brand-new',
        'status' => 'suspended',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed');

    expect(SupplyChannel::query()->where('slug', 'brand-new')->exists())->toBeFalse();
});

it('rejects status on channel update with 422 and leaves the status alone', function () {
    $channel = channelIn('active');
    Sanctum::actingAs(platformActor(), ['*'], 'platform');

    $this->putJson("/api/v1/admin/channels/{$channel->id}", ['status' => 'suspended'])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed');

    expect($channel->fresh()->status)->toBe(ChannelStatus::Active)
        ->and(ChannelEvent::query()->where('channel_id', $channel->id)->count())->toBe(0);
});
