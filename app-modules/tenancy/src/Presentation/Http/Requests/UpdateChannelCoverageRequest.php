<?php

declare(strict_types=1);

namespace Modules\Tenancy\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateChannelCoverageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'zone_ids' => ['required', 'array'],
            'zone_ids.*' => ['integer'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
