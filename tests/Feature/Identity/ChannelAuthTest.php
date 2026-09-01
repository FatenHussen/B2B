<?php

declare(strict_types=1);

use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Contracts\OtpChannel;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\ValueObjects\PhoneNumber;
use Modules\Identity\Infrastructure\Otp\FakeOtpChannel;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Tests\Support\CatalogAssert;

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);
    $this->otp = app(OtpChannel::class);
    expect($this->otp)->toBeInstanceOf(FakeOtpChannel::class);
});

it('issues a channel token for a member and 403 without membership', function () {
    $channel = SupplyChannel::factory()->create(['name' => 'نور']);
    $member = ChannelUser::factory()->forChannel($channel)->create(['phone' => '+963944000000']);
    $member->assignRole('channel_manager');

    $requested = $this->postJson('/api/v1/channel/auth/request-otp', [
        'phone' => '+963944000000',
    ]);
    CatalogAssert::ok($requested, ['otp_id']);

    /** @var FakeOtpChannel $fake */
    $fake = $this->otp;
    $code = $fake->codeFor(PhoneNumber::normalize('+963944000000'));

    $verified = $this->postJson('/api/v1/channel/auth/verify-otp', [
        'otp_id' => $requested->json('data.otp_id'),
        'code' => $code,
    ]);
    CatalogAssert::ok($verified, ['token', 'channels']);
    expect($verified->json('data.channels.0.id'))->toBe($channel->id);

    $stranger = $this->postJson('/api/v1/channel/auth/request-otp', [
        'phone' => '+963955000000',
    ]);
    CatalogAssert::ok($stranger, ['otp_id']);
    $strangerCode = $fake->codeFor(PhoneNumber::normalize('+963955000000'));

    CatalogAssert::error(
        $this->postJson('/api/v1/channel/auth/verify-otp', [
            'otp_id' => $stranger->json('data.otp_id'),
            'code' => $strangerCode,
        ]),
        403,
        'insufficient_permission',
    );
});
