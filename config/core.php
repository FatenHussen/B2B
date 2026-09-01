<?php

return [
    'money_scale' => 2,
    'default_currency' => env('APP_CURRENCY', 'SYP'),
    'idempotency_ttl_hours' => (int) env('IDEMPOTENCY_TTL_HOURS', 24),
];
