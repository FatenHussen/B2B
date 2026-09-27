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
        $cooldown = (int) config('otp.resend_cooldown', 60);

        if (! self::bypassed()) {
            $this->assertRateLimits($phone, $ip, $deviceId);

            $recent = $this->latestLive($phone, $purpose);

            if ($recent !== null && ! $recent->isExpired()) {
                $elapsed = (int) $recent->created_at->diffInSeconds(now());
                if ($elapsed < $cooldown) {
                    throw OtpException::cooldown($cooldown - $elapsed);
                }
            }
        }

        OtpRequest::query()
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = $this->generateCode($client, $purpose);
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

        $cooldown = (int) config('otp.resend_cooldown', 60);

        if (! self::bypassed()) {
            $this->assertRateLimits($otp->phone, $otp->ip, $otp->device_id);

            $elapsed = (int) $otp->created_at->diffInSeconds(now());
            if ($elapsed < $cooldown) {
                throw OtpException::cooldown($cooldown - $elapsed);
            }
        }

        $code = $this->generateCode(is_string($otp->client) ? $otp->client : null, $otp->purpose);
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

        return $this->consumeVerified($otp, $code);
    }

    /**
     * Verify the newest live row for phone + purpose (EP-AD-058 body carries
     * only `otp_code`, not `otp_id`).
     */
    public function verifyLatest(string $phone, OtpPurpose $purpose, string $code): OtpRequest
    {
        $otp = $this->latestLive(PhoneNumber::make($phone)->value, $purpose);

        if ($otp === null) {
            throw OtpException::invalid();
        }

        return $this->consumeVerified($otp, $code);
    }

    private function consumeVerified(OtpRequest $otp, string $code): OtpRequest
    {
        if ($otp->isConsumed() || $otp->isExpired()) {
            throw OtpException::expired();
        }

        if (self::bypassed()) {
            $otp->update(['consumed_at' => now()]);

            return $otp->refresh();
        }

        if ($otp->attempts >= (int) config('otp.max_attempts', 5)) {
            throw OtpException::tooManyAttempts();
        }

        $otp->increment('attempts');

        if (! $this->codeMatches($code, $otp->code_hash)) {
            throw OtpException::invalid();
        }

        $otp->update(['consumed_at' => now()]);

        return $otp->refresh();
    }

    /**
     * `otp.bypass` turns verification off for local/testing only: nothing is sent,
     * nothing is checked, and the verify requests stop validating the code's shape.
     * Staging and production ignore the flag — a launch host with APP_ENV=staging
     * must not accept every code (see docs/debt-ledger.md OTP row).
     */
    public static function bypassed(): bool
    {
        return (bool) config('otp.bypass', false)
            && in_array(app()->environment(), ['local', 'testing'], true);
    }

    private function send(string $phone, string $code, OtpPurpose $purpose, OtpChannelUsed $prefer): OtpChannelUsed
    {
        if (self::bypassed()) {
            return $prefer;
        }

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

    /**
     * Outside production, rep clients and channel login receive a fixed all-zero
     * code so dashboards/Flutter can hard-code `0000` or `000000` without reading
     * the log. Retailer (public) flows keep random codes unless `otp.bypass` is on.
     */
    private function generateCode(?string $client = null, ?OtpPurpose $purpose = null): string
    {
        $length = (int) config('otp.length', 6);

        if ($this->usesFixedZeros($client, $purpose)) {
            return str_repeat('0', $length);
        }

        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }

    private function codeMatches(string $code, string $hash): bool
    {
        if (Hash::check($code, $hash)) {
            return true;
        }

        if (app()->isProduction() || ! preg_match('/^0{4,6}$/', $code)) {
            return false;
        }

        return Hash::check(str_repeat('0', (int) config('otp.length', 6)), $hash);
    }

    private function usesFixedZeros(?string $client, ?OtpPurpose $purpose = null): bool
    {
        if (app()->isProduction()) {
            return false;
        }

        if ($purpose === OtpPurpose::ChannelLogin) {
            return true;
        }

        if (! is_string($client) || $client === '') {
            return false;
        }

        $normalized = strtolower($client);

        return str_starts_with($normalized, 'rep-')
            || str_starts_with($normalized, 'channel-');
    }
}
