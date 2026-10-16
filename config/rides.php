<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ride Reminder Lead Time
    |--------------------------------------------------------------------------
    |
    | How many hours before a ride's start time confirmed attendees are
    | reminded. The reminder command runs hourly and sends each ride's
    | reminder once, when the ride falls inside this window.
    |
    */

    'reminder_hours' => (int) env('RIDE_REMINDER_HOURS', 24),

];
