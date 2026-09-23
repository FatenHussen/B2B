<?php

declare(strict_types=1);

namespace Modules\Content\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreHelpGuideRequest extends FormRequest
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
            'audience' => ['required', Rule::in(['retailer', 'rep', 'channel', 'warehouse'])],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'status' => ['sometimes', Rule::in(['draft', 'published'])],
            'order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
