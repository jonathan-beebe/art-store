<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Delivery
    |--------------------------------------------------------------------------
    |
    | How a magic link reaches the person who asked for it. "session" flashes
    | the URL so the debug alert prints it — the development default. "mail" is
    | the hook for real email and is not implemented yet.
    |
    | Supported: "session", "mail"
    |
    */

    'delivery' => env('MAGIC_LINK_DELIVERY', 'session'),

    /*
    |--------------------------------------------------------------------------
    | Expiry
    |--------------------------------------------------------------------------
    |
    | Minutes a link stays usable after it is issued.
    |
    */

    'expiry_minutes' => (int) env('MAGIC_LINK_EXPIRY_MINUTES', 15),

];
