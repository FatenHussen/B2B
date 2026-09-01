<?php

arch('OTP channel contract is an interface')
    ->expect('Modules\Core\Contracts')
    ->toBeInterfaces();

arch('foundation modules do not use coordination modules')
    ->expect('Modules\Identity')
    ->not->toUse('Modules\Ordering')
    ->and('Modules\Tenancy')
    ->not->toUse('Modules\Ordering')
    ->and('Modules\Reference')
    ->not->toUse('Modules\Ordering');
