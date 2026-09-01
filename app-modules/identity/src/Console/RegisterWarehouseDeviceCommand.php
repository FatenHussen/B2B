<?php

declare(strict_types=1);

namespace Modules\Identity\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Domain\Models\WarehouseDevice;

final class RegisterWarehouseDeviceCommand extends Command
{
    protected $signature = 'warehouse:register-device
        {token : Plain device token}
        {pin : Four-digit PIN}
        {warehouse_id : Warehouse id}
        {channel_id : Channel id}
        {--label= : Optional device label}';

    protected $description = 'Pre-register a warehouse device (no public API).';

    public function handle(): int
    {
        $pin = (string) $this->argument('pin');

        if (! preg_match('/^\d{4}$/', $pin)) {
            $this->error('PIN must be exactly 4 digits.');

            return self::FAILURE;
        }

        WarehouseDevice::query()->updateOrCreate(
            ['device_token_hash' => hash('sha256', (string) $this->argument('token'))],
            [
                'pin_hash' => Hash::make($pin),
                'warehouse_id' => (int) $this->argument('warehouse_id'),
                'channel_id' => (int) $this->argument('channel_id'),
                'label' => $this->option('label'),
                'revoked_at' => null,
            ],
        );

        $this->info('Warehouse device registered.');

        return self::SUCCESS;
    }
}
