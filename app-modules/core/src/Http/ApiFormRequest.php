<?php

declare(strict_types=1);

namespace Modules\Core\Http;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base write-request. Controllers stay thin: type-hint this, pass validated() to an Action.
 *
 * Shape/format rules live here. Business invariants stay in Actions and fail via InvalidFields.
 * Human messages come from lang/{locale}/validation.php — do not hard-code them on every request.
 */
abstract class ApiFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
