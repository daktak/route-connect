<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notification Retention
    |--------------------------------------------------------------------------
    |
    | How many days read notifications are kept before the scheduled
    | notifications:prune command deletes them. Unread notifications are
    | never pruned automatically.
    |
    */

    'retention_days' => max(1, (int) env('NOTIFICATION_RETENTION_DAYS', 90)),

];
