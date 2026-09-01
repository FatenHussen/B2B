<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Modules\Core\Contracts\OtpChannel;
use Modules\Identity\Application\Otp\OtpDispatch;
use Modules\Identity\Domain\Enums\OtpChannelUsed;
use Modules\Identity\Domain\Enums\OtpPurpose;
use Modules\Identity\Domain\Exceptions\OtpException;
use Modules\Identity\Domain\Models\OtpRequest;
use Modules\Identity\Domain\ValueObjects\PhoneNumber;

final class OtpService
{
    public function __construct(private readonly OtpChannel $channel) {}

    public function request(
        string $phone,
        OtpPurpose $purpose,
        ?string $client = null,
        ?string $ip = null,
        ?string $deviceId = null,
        OtpChannelUsed $prefer = OtpChannelUsed::Whatsapp,
    ): OtpDispatch {
        $phone = PhoneNumber::make($phone)->value;
        $this->assertRateLimits($phone, $ip, $deviceId);

        $cooldown = (int) config('otp.resend_cooldown', 60);
        $recent = $this->latestLive($phone, $purpose);

        if ($recent !== null && ! $recent->isExpired()) {
            $elapsed = (int) $recent->created_at->diffInSeconds(now());
            if ($elapsed < $cooldown) {
                throw OtpException::cooldown($cooldown - $elapsed);
            }
        }

        OtpRequest::query()
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = $this->generateCode();
        $used = $this->send($phone, $code, $purpose, $prefer);

        $row = OtpRequest::query()->create([
            'public_id' => 'otp_'.Str::lower(Str::random(6)),
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'purpose' => $purpose,
            'channel_used' => $used,
            'client' => $client,
            'ip' => $ip,
            'device_id' => $deviceId,
            'expires_at' => now()->addSeconds((int) config('otp.ttl', 300)),
            'attempts' => 0,
        ]);

        return new OtpDispatch(
            otpId: $row->public_id,
            channelUsed: $used,
            expiresIn: (int) config('otp.ttl', 300),
            resendAfter: $cooldown,
        );
    }

    public function resend(string $otpId, OtpChannelUsed $prefer): OtpDispatch
    {
        $otp = OtpRequest::query()->where('public_id', $otpId)->first();

        if ($otp === null || $otp->isConsumed() || $otp->isExpired()) {
            throw OtpException::notFound();
        }

        $this->assertRateLimits($otp->phone, $otp->ip, $otp->device_id);

        $cooldown = (int) config('otp.resend_cooldown', 60);
        $elapsed = (int) $otp->created_at->diffInSeconds(now());
        if ($elapsed < $cooldown) {
            throw OtpException::cooldown($cooldown - $elapsed);
        }

        $code = $this->generateCode();
        $used = $this->send($otp->phone, $code, $otp->purpose, $prefer);

        $otp->forceFill([
            'code_hash' => Hash::make($code),
            'channel_used' => $used,
            'attempts' => 0,
            'expires_at' => now()->addSeconds((int) config('otp.ttl', 300)),
        ])->save();

        return new OtpDispatch(
            otpId: $otp->public_id,
            channelUsed: $used,
            expiresIn: (int) config('otp.ttl', 300),
            resendAfter: $cooldown,
        );
    }

    public function verifyById(string $otpId, string $code): OtpRequest
    {
        $otp = OtpRequest::query()->where('public_id', $otpId)->first();

        if ($otp === null) {
            throw OtpException::invalid();
        }

        if ($otp->isConsumed() || $otp->isExpired()) {
            throw OtpException::expired();
        }

        if ($otp->attempts >= (int) config('otp.max_attempts', 5)) {
            throw OtpException::tooManyAttempts();
        }

        $otp->increment('attempts');

        if (! Hash::check($code, $otp->code_hash)) {
            throw OtpException::invalid();
        }

        $otp->update(['consumed_at' => now()]);

        return $otp->refresh();
    }

    private function send(string $phone, string $code, OtpPurpose $purpose, OtpChannelUsed $prefer): OtpChannelUsed
    {
        try {
            $this->channel->send($phone, $code, $purpose->value, $prefer->value);

            return $prefer;
        } catch (\Throwable) {
            if ($prefer === OtpChannelUsed::Whatsapp) {
                $this->channel->send($phone, $code, $purpose->value, OtpChannelUsed::Sms->value);

                return OtpChannelUsed::Sms;
            }

            throw OtpException::rateLimited();
        }
    }

    private function assertRateLimits(string $phone, ?string $ip, ?string $deviceId): void
    {
        $checks = [
            'otp:phone:'.$phone => 3,
            'otp:ip:'.($ip ?: 'unknown') => 30,
        ];

        if ($deviceId) {
            $checks['otp:device:'.$deviceId] = 10;
        }

        foreach ($checks as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw OtpException::rateLimited();
            }
        }

        foreach ($checks as $key => $max) {
            RateLimiter::hit($key, 3600);
        }
    }

    private function latestLive(string $phone, OtpPurpose $purpose): ?OtpRequest
    {
        return OtpRequest::query()
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest()
            ->first();
    }

    private function generateCode(): string
    {
        $length = (int) config('otp.length', 6);
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }
}
