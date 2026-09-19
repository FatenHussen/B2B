<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;
use Modules\Identity\Application\Services\OtpService;

final class VerifyPublicOtpRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $code = $this->input('code');

        if (is_int($code) || is_float($code) || (is_string($code) && $code !== '')) {
            $this->merge(['code' => (string) $code]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'otp_id' => ['required', 'string'],
            'code' => self::codeRule(),
            'device_id' => ['required', 'string', 'max:64'],
            'device_name' => ['nullable', 'string', 'max:128'],
            'platform' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * With OTP bypassed nothing checks the code, so nothing validates it either —
     * a client may send any value or none. Production stays at 6; locally 0000 is
     * accepted so the Flutter rep screen can ship four boxes without a 422.
     *
     * @return list<string>
     */
    public static function codeRule(): array
    {
        if (OtpService::bypassed()) {
            return ['nullable', 'string', 'max:32'];
        }

        return ['required', 'string', app()->isProduction() ? 'size:6' : 'regex:/^\d{4,6}$/'];
    }
}
