<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin Emails
    |--------------------------------------------------------------------------
    |
    | A comma-separated list of user email addresses that should be granted
    | administrative abilities, such as editing or deleting any user's
    | routes, comments, or group rides. Matching is case-insensitive.
    |
    | Example: ADMIN_EMAILS="admin@example.com,owner@example.com"
    |
    */

    'emails' => collect(explode(',', (string) env('ADMIN_EMAILS', '')))
        ->map(fn ($email) => strtolower(trim($email)))
        ->filter()
        ->values()
        ->all(),

];
