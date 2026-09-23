<?php

declare(strict_types=1);

namespace Modules\Content\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PublishLegalDocumentRequest extends FormRequest
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
            'type' => ['required', Rule::in(['terms', 'privacy'])],
            'body_ar' => ['required', 'string', 'min:1'],
            'effective_from' => ['required', 'date'],
            'requires_reconsent' => ['sometimes', 'boolean'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
