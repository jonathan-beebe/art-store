<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Layer rules
|--------------------------------------------------------------------------
|
| docs/architecture.md: dependencies point inward only, and App\Domain is
| the pure core — no I/O, no clock, no random.
|
*/

arch('the domain core stays pure')
    ->expect('App\Domain')
    ->not->toUse([
        'App\Models',
        'App\Http',
        'App\Actions',
        'App\Console',
        'Illuminate\Database',
        'Illuminate\Support\Facades',
        'now',
        'time',
        'date',
        'random_int',
        'mt_rand',
        'uniqid',
    ]);

arch('the domain core depends on nothing from the framework')
    ->expect('App\Domain')
    ->not->toUse('Illuminate');

arch('actions are final, single-purpose commands')
    ->expect('App\Actions')
    ->toBeFinal()
    ->toBeInvokable();

arch('controllers do not reach around Eloquent with the DB facade')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('models do not depend on the coordination layer')
    ->expect('App\Models')
    ->not->toUse(['App\Http', 'App\Console', 'App\Actions', 'App\Policies', 'App\Events', 'App\Listeners']);

/*
|--------------------------------------------------------------------------
| Codebase-wide hygiene
|--------------------------------------------------------------------------
*/

arch('no debug output is left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die', 'exit'])
    ->not->toBeUsed();

arch('env() is read only while building config, never at runtime')
    ->expect('App')
    ->not->toUse('env');

arch('every class under App declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

/*
|--------------------------------------------------------------------------
| Pest presets
|--------------------------------------------------------------------------
*/

arch()->preset()->laravel()
    ->ignoring([
        // Action verbs like `place`, `pay`, `toggle`, `markRead` name what
        // each route does; the preset's REST-only method vocabulary reaches
        // only as far as index/show/store/update. Every other controller is
        // held to it.
        'App\Http\Controllers\Auth\AdminLoginController',
        'App\Http\Controllers\Auth\CustomerLoginController',
        'App\Http\Controllers\Auth\SellerLoginController',
        'App\Http\Controllers\Auth\SignOutController',
        'App\Http\Controllers\Seller\NotificationController',
        'App\Http\Controllers\Shop\AccountController',
        'App\Http\Controllers\Shop\CartController',
        'App\Http\Controllers\Shop\CheckoutController',
        'App\Http\Controllers\Shop\FavoriteController',
        'App\Http\Controllers\Shop\OrderPaymentController',
        // Domain enums live beside the concept they model (docs/architecture.md:
        // "domain enums name states"), not centralized under App\Enums as the
        // preset assumes. The log vocabulary — event, phase, level — is named
        // by the same rule, beside the formatter that writes it, and the
        // analytics vocabulary beside the store that writes it.
        'App\Domain',
        'App\Logging',
        'App\Analytics',
        // Named for the artisan command each registers (`payouts:run`,
        // `orders:sweep`), not suffixed `Command`.
        'App\Console\Commands\RunWeeklyPayouts',
        'App\Console\Commands\SweepOrders',
        // A delivery channel is not a notification, and Laravel's own docs
        // home for a custom channel is App\Notifications\Channels.
        'App\Notifications\Channels',
        // The storefront's abstract form request carries the visitor its
        // children validate against; the rules belong to the children.
        'App\Http\Requests\Shop\ShopRequest',
    ]);

arch()->preset()->security();
