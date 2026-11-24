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

    // Content packages (F6, D67, D70): limits checked before anything is unpacked or decoded.
    // Most of a large package is images; each JSON document stays small because PHP needs about
    // ten times a document's size in memory to decode it.
    'content_packages' => [
        // Where uploads and exports live between steps (D70). The api and the worker are separate
        // containers on AWS, so production needs the S3 `transfers` disk; `local` only on one machine.
        'disk' => env('TRANSFERS_DISK', 'local'),
        'part_bytes' => 8 * 1024 * 1024,                // one upload part (S3 needs at least 5 MB)
        'ttl_days' => 7,                                // files and rows are removed after this
        'max_bytes' => 250 * 1024 * 1024,                // the uploaded file, compressed
        'max_uncompressed_bytes' => 1024 * 1024 * 1024, // every zip entry together
        'max_entries' => 5000,
        'max_ratio' => 100,                             // uncompressed / compressed, per entry over 1 MB
        'max_document_bytes' => 10 * 1024 * 1024,       // one JSON document (the manifest or a $file .json)
        'max_paste_bytes' => 2 * 1024 * 1024,           // JSON pasted into the validate-only check; larger goes as a file
        'max_text_bytes' => 1024 * 1024,                // one $file text
        'max_json_depth' => 64,
        'max_documents' => 60,                          // lessons in one `course` package
        'max_operations' => 200,
        'text_extensions' => ['md', 'txt', 'json', 'html', 'css', 'js', 'ts', 'tsx', 'py', 'sh', 'ps1', 'php', 'go', 'rs', 'c', 'h', 'java', 'sql', 'yaml', 'yml', 'toml', 'ini', 'conf', 'log', 'csv', 'xml'],
    ],

    // Shared secret the Next.js server sends with its public, server-side reads (X-Internal-Token).
    // On AWS every such read comes from the web task's one address, so it must not count against
    // the per-address `api` limit (docs/SECURITY_REVIEW.md S8). Empty locally.
    'internal_api_token' => env('INTERNAL_API_TOKEN'),

    // Demo admin created by DemoUserSeeder (admin@cybercompass.test). Skipped when empty.
    'demo_admin_password' => env('DEMO_ADMIN_PASSWORD'),

];
