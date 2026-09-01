<?php

use Modules\Tenancy\Domain\Models\SupplyChannel;

return [
    'column' => 'supply_channel_id',
    'tenant_model' => SupplyChannel::class,
    'header' => 'X-Channel-Id',
];
