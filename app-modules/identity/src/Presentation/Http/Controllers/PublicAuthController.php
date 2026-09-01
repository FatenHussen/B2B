<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\ApiController;
use Modules\Identity\Application\Actions\VerifyAppOtp;
use Modules\Identity\Application\Services\OtpService;
use Modules\Identity\Domain\Enums\OtpChannelUsed;
use Modules\Identity\Domain\Enums\OtpPurpose;
use Modules\Identity\Presentation\Http\Requests\RequestPublicOtpRequest;
use Modules\Identity\Presentation\Http\Requests\ResendOtpRequest;
use Modules\Identity\Presentation\Http\Requests\VerifyPublicOtpRequest;

final class PublicAuthController extends ApiController
{
    public function requestOtp(RequestPublicOtpRequest $request, OtpService $otp): JsonResponse
    {
        $dispatch = $otp->request(
            phone: $request->string('phone')->toString(),
            purpose: OtpPurpose::from($request->string('purpose')->toString()),
            client: $request->input('client'),
            ip: $request->ip(),
            deviceId: $request->header('X-Device-Id'),
        );

        return $this->ok([
            'otp_id' => $dispatch->otpId,
            'channel_used' => $dispatch->channelUsed->value,
            'expires_in' => $dispatch->expiresIn,
            'resend_after' => $dispatch->resendAfter,
        ]);
    }

    public function verifyOtp(VerifyPublicOtpRequest $request, VerifyAppOtp $action): JsonResponse
    {
        return $this->ok($action(
            $request->string('otp_id')->toString(),
            $request->string('code')->toString(),
            $request->string('device_id')->toString(),
            $request->input('device_name'),
            $request->input('platform'),
        ));
    }

    public function resendOtp(ResendOtpRequest $request, OtpService $otp): JsonResponse
    {
        $dispatch = $otp->resend(
            $request->string('otp_id')->toString(),
            OtpChannelUsed::from($request->string('prefer_channel')->toString()),
        );

        return $this->ok([
            'channel_used' => $dispatch->channelUsed->value,
            'resend_after' => $dispatch->resendAfter,
        ]);
    }
}
