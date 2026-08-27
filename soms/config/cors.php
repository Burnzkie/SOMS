<?php

$origins = array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', '')));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],

    // Empty array if CORS_ALLOWED_ORIGINS is unset — never '*'.
    // render.yaml aborts the deploy if this env var is missing (10-Mobile-Deployment.md).
    'allowed_origins' => $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'X-CSRF-TOKEN', 'Authorization', 'Accept'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
