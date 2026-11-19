<?php

/*
| CyberCompass application settings. Read through config(), never env(), so they
| survive `config:cache` in production.
*/

return [

    // Disk for user uploads (avatars, later lesson media): `public` locally, `s3` on AWS.
    'uploads_disk' => env('UPLOADS_DISK', 'public'),

    'avatar' => [
        'size' => 256,       // stored as a square WebP of this many pixels
        'max_kb' => 2048,    // upload limit
    ],

    // Demo learner created by DemoUserSeeder (local/demo environments only).
    'demo_user_password' => env('DEMO_USER_PASSWORD'),

];
