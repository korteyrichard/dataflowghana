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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'bulkclix' => [
        'api_key' => env('BULKCLIX_API_KEY'),
        'sender_id' => env('BULKCLIX_SENDER_ID'),
    ],

    'moolre' => [
        'api_key' => env('MOOLRE_API_KEY'),
    ],

    'datamaster' => [
        'base_url' => env('DATAMASTER_BASE_URL', 'https://user.datamastagh.shop/developer/api/v2'),
        'secret_key' => env('DATAMASTER_SECRET_KEY'),
        'public_key' => env('DATAMASTER_PUBLIC_KEY'),
    ],

    'dataeasy' => [
        'base_url' => env('DATAEASY_BASE_URL', 'https://dataeasy.onrender.com/api/v1'),
        'api_key' => env('DATAEASY_API_KEY'),
    ],

    'datasource' => [
        'base_url' => env('DATASOURCE_BASE_URL', 'https://datasourcegh.com'),
        'api_key' => env('DATASOURCE_API_KEY'),
        'secret_key' => env('DATASOURCE_SECRET_KEY'),
    ],

    'codecraft_mtn' => [
        'api_key' => env('CODECRAFT_MTN_API_KEY') ?: env('CODE_CRAFTER_API_KEY', ''),
        'client_email' => env('CODE_CRAFTER_CLIENT_EMAIL', ''),
    ],

];
