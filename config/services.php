<?php

$matrixHomeserver = trim((string) env('MATRIX_HOMESERVER_URL', ''));
$matrixHomeserver = preg_replace('#^//+#', '', $matrixHomeserver);
if ($matrixHomeserver !== '' && ! preg_match('#^https?://#i', $matrixHomeserver)) {
    $matrixHomeserver = 'https://'.$matrixHomeserver;
}
$matrixHomeserver = rtrim($matrixHomeserver, '/');

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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI'),
    ],

    'strava' => [
        'client_id' => env('STRAVA_CLIENT_ID'),
        'client_secret' => env('STRAVA_CLIENT_SECRET'),
        'redirect' => env('STRAVA_REDIRECT_URI'),
    ],

    'open_meteo' => [
        'base_url' => env('OPEN_METEO_BASE_URL', 'https://api.open-meteo.com/v1/forecast'),
        'cache_ttl' => (int) env('WEATHER_CACHE_TTL', 1800),
        'forecast_days' => (int) env('WEATHER_FORECAST_DAYS', 16),
        'air_quality_url' => env('OPEN_METEO_AIR_QUALITY_URL', 'https://air-quality-api.open-meteo.com/v1/air-quality'),
        'air_quality_cache_ttl' => (int) env('AIR_QUALITY_CACHE_TTL', 1800),
        'air_quality_forecast_days' => (int) env('AIR_QUALITY_FORECAST_DAYS', 5),
        'timeout' => (int) env('OPEN_METEO_TIMEOUT', 5),
        'retry_attempts' => (int) env('OPEN_METEO_RETRIES', 1),
        'failure_cache_ttl' => (int) env('OPEN_METEO_FAILURE_CACHE_TTL', 60),
    ],

    'valhalla' => [
        'base_url' => env('VALHALLA_BASE_URL', 'https://valhalla1.openstreetmap.de'),
        'timeout' => (int) env('VALHALLA_TIMEOUT', 30),
    ],

    'discord' => [
        'webhook_url' => env('DISCORD_WEBHOOK_URL'),
        'invite_url' => env('DISCORD_INVITE_URL'),
        'timeout' => (int) env('DISCORD_TIMEOUT', 5),
    ],

    'matrix' => [
        'homeserver_url' => $matrixHomeserver,
        'username' => env('MATRIX_USERNAME'),
        'password' => env('MATRIX_PASSWORD'),
        'room_ids' => array_filter(array_map('trim', explode(',', (string) env('MATRIX_ROOM_IDS', '')))),
        'invite_url' => env('MATRIX_INVITE_URL'),
        'timeout' => (int) env('MATRIX_TIMEOUT', 5),
        'retry_attempts' => (int) env('MATRIX_RETRY_ATTEMPTS', 2),
        'max_retry_delay_ms' => (int) env('MATRIX_MAX_RETRY_DELAY_MS', 30000),
    ],

];
