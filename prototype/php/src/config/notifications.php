<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Channels
    |--------------------------------------------------------------------------
    |
    | The channels every seller and customer notification is delivered on,
    | comma separated. "database" fills the in-app inbox each site renders;
    | adding "mail" sends the same message as email.
    |
    | Supported: "database", "mail"
    |
    */

    'channels' => explode(',', (string) env('NOTIFICATION_CHANNELS', 'database')),

];
