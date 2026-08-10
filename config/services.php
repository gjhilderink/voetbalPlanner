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

    'mcp' => [
        'base_url' => env('MCP_BASE_URL', ''),
        'api_key' => env('MCP_API_KEY', ''),
        'timeout' => env('MCP_TIMEOUT', 30),
    ],

    // Firebase Cloud Messaging (push). 'credentials' = pad naar de service-account
    // JSON (Firebase Console → Projectinstellingen → Serviceaccounts → Nieuwe
    // privésleutel). Absoluut pad, of relatief t.o.v. de projectroot. Leeg = push uit.
    'fcm' => [
        'credentials' => env('FCM_CREDENTIALS', storage_path('app/firebase-service-account.json')),
    ],

];
