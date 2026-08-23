<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Seller;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | The storefront is the larger of the two sites, so an unqualified
    | auth() call resolves the customer.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'customer'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | One guard per site. Sign-in is passwordless — a guard only ever receives
    | an already-verified model from the magic-link flow.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'seller' => [
            'driver' => 'session',
            'provider' => 'sellers',
        ],

        'customer' => [
            'driver' => 'session',
            'provider' => 'customers',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'sellers' => [
            'driver' => 'eloquent',
            'model' => Seller::class,
        ],

        'customers' => [
            'driver' => 'eloquent',
            'model' => Customer::class,
        ],
    ],

];
