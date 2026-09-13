<?php

/*
|--------------------------------------------------------------------------
| OTP (phone login)
|--------------------------------------------------------------------------
|
| Login is passwordless: a short code is sent to the user's WhatsApp number
| (SRS FR-RT-004 / DOC-02 §10). During development the `log` channel writes
| the code to the log instead of calling WhatsApp — the real WhatsApp Business
| integration is wired last. Switch `channel` to `whatsapp` once the token and
| phone id below are filled in.
|
*/

return [
    'ttl' => (int) env('OTP_TTL_SECONDS', 300),
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
    'length' => (int) env('OTP_LENGTH', 6),
    'resend_cooldown' => (int) env('OTP_RESEND_COOLDOWN', 60),

    // log | whatsapp
    'channel' => env('OTP_CHANNEL', 'log'),

    /*
    | Development switch — OTP verification off.
    |
    | While the clients are being built nobody can read a code out of the log on a
    | shared server, and three requests per phone per hour is burned in a minute of
    | testing. With `bypass` on the flow is unchanged (request-otp → verify-otp, same
    | payloads, same otp_id) but the code is never sent or checked: any 6-character
    | `code` verifies, and the cooldown and rate limits are skipped.
    |
    | It is ignored when APP_ENV=production, whatever the env file says — an OTP that
    | accepts every code is not a login. Flip OTP_BYPASS=false to turn OTP back on.
    */
    'bypass' => (bool) env('OTP_BYPASS', false),

    'whatsapp' => [
        'token' => env('WHATSAPP_TOKEN'),
        'phone_id' => env('WHATSAPP_PHONE_ID'),
        'api_base' => env('WHATSAPP_API_BASE', 'https://graph.facebook.com/v21.0'),
    ],
];
