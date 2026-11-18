<?php

declare(strict_types=1);

/*
| Firebase Cloud Messaging — server credentials only.
|
| Firebase Console → Project settings → Service accounts → Generate new private key.
| Point FIREBASE_CREDENTIALS at that JSON file (or paste the JSON object).
| This is not google-services.json and not the client apiKey. Those stay in the
| Flutter app. Empty means push is not sent and the delivery log says so.
|
*/

return [
    'credentials' => env('FIREBASE_CREDENTIALS'),
];
