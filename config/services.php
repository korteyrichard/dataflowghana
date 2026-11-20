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

    'dataeasy' => [
        'base_url' => env('DATAEASY_BASE_URL', 'https://dataeasy.onrender.com/api/v1'),
        'api_key' => env('DATAEASY_API_KEY'),
    ],

    'datasource' => [
        'base_url' => env('DATASOURCE_BASE_URL', 'https://datasourcegh.com'),
        'api_key' => env('DATASOURCE_API_KEY'),
        'secret_key' => env('DATASOURCE_SECRET_KEY'),
    ],

    'codecraft' => [
        'base_url' => env('CODE_CRAFTER_BASE_URL', 'https://api.codecraftnetwork.com/api'),
        'api_key' => env('CODE_CRAFTER_API_KEY', ''),
    ],

    'codecraft_mtn' => [
        'api_key' => env('CODECRAFT_MTN_API_KEY') ?: env('CODE_CRAFTER_API_KEY', ''),
        'client_email' => env('CODE_CRAFTER_CLIENT_EMAIL', ''),
    ],

    'datamasta' => [
        'api_key' => env('DATAMASTA_API_KEY', ''),
    ],

    'rexamart' => [
        'api_key' => env('REXAMART_API_KEY', ''),
    ],

    'bundleportal' => [
        'base_url' => env('BUNDLEPORTAL_BASE_URL', 'https://api.bundleportal.com/v2'),
        'api_key'  => env('BUNDLEPORTAL_API_KEY', ''),
        'webhook_secret' => env('BUNDLEPORTAL_WEBHOOK_SECRET', ''),
    ],

    'dataworks' => [
        'base_url' => env('DATAWORKS_BASE_URL', 'https://dataworksgh.com/api/v1'),
        'api_key' => env('DATAWORKS_API_KEY', ''),
    ],

    'maxdata' => [
        'base_url' => env('MAXDATA_BASE_URL', 'https://maxdata-hub.com/api/v1'),
        'api_key' => env('MAXDATA_API_KEY', ''),
    ],

];
