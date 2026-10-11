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

    'open_meteo' => [
        'base_url' => env('OPEN_METEO_BASE_URL', 'https://api.open-meteo.com/v1/forecast'),
        'cache_ttl' => (int) env('WEATHER_CACHE_TTL', 1800),
        'forecast_days' => (int) env('WEATHER_FORECAST_DAYS', 16),
        'air_quality_url' => env('OPEN_METEO_AIR_QUALITY_URL', 'https://air-quality-api.open-meteo.com/v1/air-quality'),
        'air_quality_cache_ttl' => (int) env('AIR_QUALITY_CACHE_TTL', 1800),
        'air_quality_forecast_days' => (int) env('AIR_QUALITY_FORECAST_DAYS', 5),
    ],

    'valhalla' => [
        'base_url' => env('VALHALLA_BASE_URL', 'https://valhalla1.openstreetmap.de'),
        'timeout' => (int) env('VALHALLA_TIMEOUT', 30),
    ],

];
