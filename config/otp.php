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

    'whatsapp' => [
        'token' => env('WHATSAPP_TOKEN'),
        'phone_id' => env('WHATSAPP_PHONE_ID'),
        'api_base' => env('WHATSAPP_API_BASE', 'https://graph.facebook.com/v21.0'),
    ],
];
