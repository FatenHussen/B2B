<?php

declare(strict_types=1);

namespace Modules\Tenancy\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Http\ApiFormRequest;
use Modules\Tenancy\Domain\Enums\ChannelStatus;

/**
 * EP-AD-054: `to_status` and a reason.
 *
 * `to_status` must be a channel status at all before the matrix is consulted — a name
 * that is not a state is a 422, a state the matrix does not allow from here is a 409.
 * The reason is required because the lifecycle records it, and a transition it cannot
 * record is one it does not make.
 */
final class TransitionChannelRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'to_status' => ['required', Rule::enum(ChannelStatus::class)],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
