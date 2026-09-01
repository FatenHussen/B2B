<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Illuminate\Support\Facades\Hash;
use Modules\Core\Contracts\AccessCatalog;
use Modules\Core\Contracts\WarehouseDirectory;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Identity\Application\Services\TokenIssuer;
use Modules\Identity\Domain\Models\WarehouseDevice;
use Modules\Identity\Domain\Models\WarehouseUser;

final class DeviceLogin
{
    public function __construct(
        private readonly TokenIssuer $tokens,
        private readonly AccessCatalog $access,
        private readonly WarehouseDirectory $warehouses,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(string $deviceToken, string $pin): array
    {
        $hash = hash('sha256', $deviceToken);
        $device = WarehouseDevice::query()->where('device_token_hash', $hash)->first();

        if ($device === null || $device->isRevoked() || ! Hash::check($pin, $device->pin_hash)) {
            throw new DomainException(__('identity.invalid_device'), 'unauthenticated', 401);
        }

        $user = $device->user ?? WarehouseUser::query()->create([
            'name' => $device->label ?: 'Warehouse device',
        ]);

        if ($device->warehouse_user_id === null) {
            $device->forceFill(['warehouse_user_id' => $user->id])->save();
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $warehouse = $this->warehouses->find($device->warehouse_id);

        return [
            'token' => $this->tokens->issue($user, ['*'], $deviceToken, $device->label, 'warehouse'),
            'warehouse' => [
                'id' => $device->warehouse_id,
                'name' => $warehouse['name'] ?? $device->label,
            ],
            'permissions' => $this->access->permissionsFor($user),
        ];
    }
}
