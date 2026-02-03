<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | This enables Flutter Web (localhost:*), local SPA dev, etc. to call the API
    | and load media via Laravel routes.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'media/*'],

    'allowed_methods' => ['*'],

    // Allow local dev origins (Flutter web uses localhost:<random_port>)
    'allowed_origins' => [],
    'allowed_origins_patterns' => [
        '/^http:\\/\\/localhost(:\\d+)?$/',
        '/^http:\\/\\/127\\.0\\.0\\.1(:\\d+)?$/',
        '/^http:\\/\\/localapp\\.test$/',
    ],

    'allowed_headers' => ['*'],

    // Expose common headers if you need them in web clients
    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];

