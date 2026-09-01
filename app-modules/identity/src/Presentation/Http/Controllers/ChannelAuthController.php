<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\ApiController;
use Modules\Identity\Application\Actions\VerifyChannelOtp;
use Modules\Identity\Application\Services\OtpService;
use Modules\Identity\Domain\Enums\OtpPurpose;
use Modules\Identity\Presentation\Http\Requests\RequestChannelOtpRequest;
use Modules\Identity\Presentation\Http\Requests\VerifyChannelOtpRequest;

final class ChannelAuthController extends ApiController
{
    public function requestOtp(RequestChannelOtpRequest $request, OtpService $otp): JsonResponse
    {
        $dispatch = $otp->request(
            phone: $request->string('phone')->toString(),
            purpose: OtpPurpose::ChannelLogin,
            ip: $request->ip(),
            deviceId: $request->header('X-Device-Id'),
        );

        return $this->ok(['otp_id' => $dispatch->otpId]);
    }

    public function verifyOtp(VerifyChannelOtpRequest $request, VerifyChannelOtp $action): JsonResponse
    {
        return $this->ok($action(
            $request->string('otp_id')->toString(),
            $request->string('code')->toString(),
        ));
    }
}
