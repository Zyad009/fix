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

    'gowa' => [
        'url' => env('GOWA_URL'),
        'username' => env('GOWA_USERNAME'),
        'password' => env('GOWA_PASSWORD'),
        'country_code' => env('GOWA_COUNTRY_CODE', '20'),
    ],

    'browsershot' => [
        'node' => env('BROWSERSHOT_NODE_BINARY'),
        'npm' => env('BROWSERSHOT_NPM_BINARY'),
        'chrome' => env('BROWSERSHOT_CHROME_PATH'),
        'no_sandbox' => (bool) env('BROWSERSHOT_NO_SANDBOX', false),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
