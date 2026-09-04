<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Support desk
|--------------------------------------------------------------------------
|
| The seller-facing desk facts the support hub shows beside the two admins
| the AdminSeeder seeds — names and titles are the admin rows themselves,
| this file carries only what has no row of its own to live on. A fact
| that is not known yet keeps its bracketed placeholder rather than a
| blank field.
|
*/

return [

    'role' => env('SUPPORT_ROLE', 'Seller support'),

    'reply_time' => env('SUPPORT_REPLY_TIME', 'under two hours, Monday to Friday, 9:00 to 17:00 UK time'),

    'email' => env('SUPPORT_EMAIL', 'sellers@artstore.example'),

    'phone' => env('SUPPORT_PHONE', '[PHONE NUMBER]'),

    'phone_hours' => env('SUPPORT_PHONE_HOURS', 'weekdays 9:00 to 17:00'),

    'booking_url' => env('SUPPORT_BOOKING_URL', '[BOOKING URL]'),

    /*
    | Weekday hours (Monday to Friday) the desk reads its own presence
    | against — "HH:MM", in the app's own timezone.
    */
    'hours' => [
        'opens_at' => env('SUPPORT_HOURS_OPEN', '09:00'),
        'closes_at' => env('SUPPORT_HOURS_CLOSE', '17:00'),
    ],

];
