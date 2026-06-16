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

    /*
    |--------------------------------------------------------------------------
    | Hasura (Service 1 - Katalog Event)
    |--------------------------------------------------------------------------
    | Endpoint GraphQL Service 1. Dipanggil worker untuk mutation kuota atomik.
    */
    'hasura' => [
        'url' => env('HASURA_URL', 'http://hasura:8080/v1/graphql'),
        'admin_secret' => env('HASURA_ADMIN_SECRET', 'myadminsecret'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Booking Service (Service 2)
    |--------------------------------------------------------------------------
    | Dipakai worker untuk callback update status booking (opsional).
    */
    'booking_service' => [
        'url' => env('BOOKING_SERVICE_URL', 'http://booking_service:8000/api/bookings'),
    ],

];
