<?php

declare(strict_types=1);

namespace Modules\Notification\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class RegisterPushTokenRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'string', 'in:android,ios'],
        ];
    }
}
