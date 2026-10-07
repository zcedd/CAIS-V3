<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'everify' => [
        'enabled' => (bool) env('EVERIFY_ENABLED', false),
        'base_url' => rtrim((string) env('EVERIFY_BASE_URL', 'https://ws.everify.gov.ph/api/dev'), '/'),
        'client_id' => env('EVERIFY_CLIENT_ID'),
        'client_secret' => env('EVERIFY_CLIENT_SECRET'),
        'public_key' => env('EVERIFY_PUBLIC_KEY'),
        'liveness_sdk_url' => env(
            'EVERIFY_LIVENESS_SDK_URL',
            'https://liveness.everify.gov.ph/js/everify-liveness-sdk.min.js',
        ),
        'timeout' => (int) env('EVERIFY_TIMEOUT', 15),
        'connect_timeout' => (int) env('EVERIFY_CONNECT_TIMEOUT', 5),
        'token_cache_seconds' => (int) env('EVERIFY_TOKEN_CACHE_SECONDS', 1500),
        'biometrics' => array_values(array_filter(array_map(
            static fn (string $method): string => strtolower(trim($method)),
            explode(',', (string) env('EVERIFY_BIOMETRICS', 'face,fingerprint')),
        ))),
        'fingerprint_ports' => array_values(array_filter(array_map(
            static fn (string $port): int => (int) trim($port),
            explode(',', (string) env('EVERIFY_FINGERPRINT_PORTS', '4301,4302')),
        ))),
        'fingerprint_env' => env('EVERIFY_FINGERPRINT_ENV', 'Production'),
        'fingerprint_domain_uri' => env(
            'EVERIFY_FINGERPRINT_DOMAIN_URI',
            'https://apps.pdccl.philsys.gov.ph',
        ),
        'fingerprint_device_id' => env('EVERIFY_FINGERPRINT_DEVICE_ID'),
    ],

];
