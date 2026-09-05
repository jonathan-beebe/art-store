<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Stale orders
    |--------------------------------------------------------------------------
    |
    | How many hours an order may sit at pending_verification — a guest who
    | never opened their magic link — before `sweep:orders` cancels it and
    | hands its stock back to the storefront.
    |
    */

    'stale_hours' => (int) env('STALE_ORDER_HOURS', 24),

];
