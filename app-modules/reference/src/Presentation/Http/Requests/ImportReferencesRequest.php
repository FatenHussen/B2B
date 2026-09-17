<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Http\ApiFormRequest;
use Modules\Reference\Domain\Enums\ReferenceEntity;

/**
 * EP-AD-041 — multipart: `type`, `file` (CSV) and `dry_run`.
 *
 * Currencies are not importable: `ad.refs.import` is not `ad.refs.currency`, and BE-R08
 * keeps the currency permission apart from the general refs family.
 */
final class ImportReferencesRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_diff(ReferenceEntity::values(), [ReferenceEntity::Currencies->value]))],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'dry_run' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Multipart carries booleans as strings; validate the boolean, not the spelling.
        $raw = $this->input('dry_run');
        if (is_string($raw)) {
            $parsed = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($parsed !== null) {
                $this->merge(['dry_run' => $parsed]);
            }
        }
    }
}
