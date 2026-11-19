<?php

/*
| CORS for the Next.js frontend. Credentials are allowed because authentication uses
| Sanctum's cookie session (docs/ARCHITECTURE.md §4), which also means origins must be
| listed explicitly. A wildcard origin is not allowed with credentials.
*/

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map(
        fn (string $origin) => rtrim(trim($origin), '/'),
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', 'http://localhost:3000'))),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Accept-Language', 'Content-Type', 'X-Requested-With', 'X-XSRF-TOKEN'],

    'exposed_headers' => ['Retry-After'],

    'max_age' => 600,

    'supports_credentials' => true,

];
