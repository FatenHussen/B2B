<?php

declare(strict_types=1);

namespace Modules\Notification\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class SendNotificationRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'icon' => ['nullable', 'string', 'max:32'],
            'image' => ['nullable', 'string', 'max:255'],
            'action' => ['nullable', 'array'],
            'action.type' => ['nullable', 'string'],
            'action.target' => ['nullable'],
            'targeting' => ['required', 'array'],
            'targeting.type' => ['required', 'string'],
            'targeting.ids' => ['nullable', 'array'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['string', 'in:push,in_app,whatsapp'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
