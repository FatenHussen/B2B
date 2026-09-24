<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class ResolveWarehouseSyncConflictRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'conflict_id' => ['required', 'string', 'max:80'],
            'resolution' => ['required', 'string', 'in:server_wins,keep_server,keep_client,client_wins'],
        ];
    }
}
