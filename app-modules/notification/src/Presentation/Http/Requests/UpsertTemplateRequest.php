<?php

declare(strict_types=1);

namespace Modules\Notification\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class UpsertTemplateRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_key' => ['required', 'string', 'max:64'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'enabled' => ['required', 'boolean'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['string', 'in:push,in_app,whatsapp'],
        ];
    }
}
