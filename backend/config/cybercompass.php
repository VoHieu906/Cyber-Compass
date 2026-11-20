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

    'content' => [
        // script-3d lesson blocks: admin-written Three.js run only in the client sandbox
        // (docs/SANDBOXED_3D.md). false refuses new ones and shows learners the text description.
        'scripted_3d' => (bool) env('CONTENT_SCRIPTED_3D', true),
    ],

    // Demo admin created by DemoUserSeeder (admin@cybercompass.test). Skipped when empty.
    'demo_admin_password' => env('DEMO_ADMIN_PASSWORD'),

];
