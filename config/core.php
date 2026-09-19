<?php

return [
    'money_scale' => 2,
    'default_currency' => env('APP_CURRENCY', 'SYP'),
    'idempotency_ttl_hours' => (int) env('IDEMPOTENCY_TTL_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Idempotency lock
    |--------------------------------------------------------------------------
    |
    | BE-C13. A row in `processing` is held by the worker running the request
    | for this many seconds. A worker that dies mid-request — a fatal error, a
    | killed process — never writes the completion, and its row used to answer
    | 409 to every retry for the full 24-hour TTL. Once the lock has lapsed the
    | next retry takes the row over and runs. Longer than any request is allowed
    | to execute (PHP's default max_execution_time is 30 seconds); shorter than
    | a person's patience.
    |
    */
    'idempotency_lock_seconds' => (int) env('IDEMPOTENCY_LOCK_SECONDS', 60),

    /*
    |--------------------------------------------------------------------------
    | Idempotency exemption list
    |--------------------------------------------------------------------------
    |
    | BE-C03 §5. Every write accepts X-Idempotency-Key except the paths below,
    | which are the credential-establishing endpoints: a client cannot hold a
    | key before it holds a session, and replaying an OTP request must issue a
    | fresh code rather than replay the previous response.
    |
    | This list is a published contract — the frontend kit omits the header on
    | exactly these paths. Adding to it is an API change. Patterns are matched
    | with Request::is(), so they are full paths without a leading slash.
    |
    */
    'idempotency_exempt' => [
        'api/v1/public/auth/request-otp',
        'api/v1/public/auth/verify-otp',
        'api/v1/public/auth/resend-otp',
        'api/v1/platform/auth/login',
        'api/v1/platform/auth/2fa/verify',
        'api/v1/channel/auth/request-otp',
        'api/v1/channel/auth/verify-otp',
        'api/v1/warehouse/auth/device-login',
    ],
];
