<?php

$cancelWindow = env('CANCEL_WINDOW_SECONDS');

return [

    /*
    |--------------------------------------------------------------------------
    | Cancel Window
    |--------------------------------------------------------------------------
    |
    | How many seconds a buyer has to cancel a newly placed order before it is
    | confirmed and the admin and farmers are notified. The value is stored on
    | each order, so changing it only affects orders placed afterwards. An
    | empty or non-numeric value (e.g. an empty Vercel variable) uses 20.
    |
    */

    'cancel_window_seconds' => is_numeric($cancelWindow) ? max(0, (int) $cancelWindow) : 20,

    /*
    |--------------------------------------------------------------------------
    | Cron Secret
    |--------------------------------------------------------------------------
    |
    | Protects GET /api/cron/confirm-orders. Vercel Cron sends it as a bearer
    | token when CRON_SECRET is set on the project. Without it the route is
    | disabled.
    |
    */

    'cron_secret' => env('CRON_SECRET') ?: null,

];
