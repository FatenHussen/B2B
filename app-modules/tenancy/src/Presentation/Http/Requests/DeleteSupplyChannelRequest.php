<?php

declare(strict_types=1);

namespace Modules\Tenancy\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DeleteSupplyChannelRequest extends FormRequest
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
            'password_confirmation' => ['required', 'string'],
            'otp_code' => ['required', 'string'],
            'typed_name' => ['required', 'string', 'max:255'],
            'second_approver_id' => ['sometimes', 'integer'],
            'approval_request_id' => ['sometimes', 'integer'],
            'approval_reason' => ['required_with:approval_request_id', 'string', 'min:3', 'max:500'],
        ];
    }
}
