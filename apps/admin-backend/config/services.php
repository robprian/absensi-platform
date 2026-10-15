<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    /*
    |--------------------------------------------------------------------------
    | tDocs — private document storage
    |--------------------------------------------------------------------------
    | The admin password NEVER leaves the server: TdocsService calls tDocs
    | server-side, and file downloads are proxied through short-lived
    | signed URLs. TDOCS_CDN_PUBLIC=0 must be set on the tDocs service.
    */
    'tdocs' => [
        'base_url' => env('TDOCS_BASE_URL', 'http://tdocs:8080'),
        'password' => env('TDOCS_ADMIN_PASSWORD'),
        'timeout' => env('TDOCS_TIMEOUT', 30),
        'cdn_public' => env('TDOCS_CDN_PUBLIC', '0'),
    ],

];
