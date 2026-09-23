<?php

declare(strict_types=1);

namespace Modules\Sync\Application\Actions;

use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Sync\Domain\Models\SyncOperation;

final class ResolveSyncConflict
{
    /**
     * @param  array{conflict_id: string, resolution: string}  $data
     * @return array{success: true}
     */
    public function __invoke(object $user, array $data): array
    {
        $row = SyncOperation::query()
            ->where('app_user_id', (int) $user->getAuthIdentifier())
            ->where('status', 'conflict')
            ->where(function ($q) use ($data): void {
                $q->whereKey($data['conflict_id'])->orWhere('op_id', $data['conflict_id']);
            })
            ->first();
        if ($row === null) {
            throw DomainException::of(ErrorCode::NotFound);
        }

        $keepServer = in_array($data['resolution'], ['server_wins', 'keep_server'], true);
        $row->status = $keepServer ? 'applied' : 'rejected';
        $row->save();

        return ['success' => true];
    }
}
