<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

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
        'App\Admin',
        'App\Analytics',
        'App\Console',
        'App\Seller',
        'App\Shop',
        'App\Orders',
        'App\Configurator',
        'App\RateLimiting',
        'App\Identifiers',
        'App\Paging',
        'App\Theme',
        'App\View',
        'Illuminate\Database',
        'Illuminate\Support\Facades',
        'now',
        'time',
        'date',
        'random_int',
        'mt_rand',
        'mt_srand',
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

arch('adapters do not depend on the coordination or entry layers')
    ->expect([
        'App\Admin',
        'App\Analytics',
        'App\Configurator',
        'App\Identifiers',
        'App\Logging',
        'App\Models',
        'App\Notifications',
        'App\Observers',
        'App\Orders',
        'App\Paging',
        'App\RateLimiting',
        'App\Seller',
        'App\Shop',
        'App\Theme',
        'App\View',
    ])
    ->not->toUse(['App\Http', 'App\Actions', 'App\Console', 'App\Mcp', 'App\Providers']);

arch('the MCP tools read through the admin readers, never the DB facade')
    ->expect('App\Mcp')
    ->not->toUse('Illuminate\\Support\\Facades\\DB');

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

/**
 * Pest's arch DSL has no expectation for constructor visibility, so this
 * reflects on every class under app/. A class with an instance property or
 * an instance public method holds state or an instance API; it is
 * skipped. A class with no public static method has nothing to gate; it
 * is skipped. The rest are static-only helpers and must keep a private
 * constructor.
 */
it('every static-only class under App keeps a private constructor', function (): void {
    $base = dirname(__DIR__);

    $offenders = [];

    foreach (Finder::create()->files()->name('*.php')->notName('*Test.php')->in($base.'/app') as $file) {
        $relative = 'app/'.ltrim(str_replace($base.'/app', '', $file->getPathname()), '/');
        $fqcn = 'App\\'.str_replace('/', '\\', substr($relative, 4, -4));

        if (! class_exists($fqcn)) {
            continue;
        }

        $reflection = new ReflectionClass($fqcn);

        if ($reflection->isInterface() || $reflection->isEnum() || $reflection->isTrait() || $reflection->isAbstract()) {
            continue;
        }

        if (collect($reflection->getProperties())->contains(fn (ReflectionProperty $property): bool => ! $property->isStatic())) {
            continue;
        }

        $publicMethods = collect($reflection->getMethods(ReflectionMethod::IS_PUBLIC))
            ->reject(fn (ReflectionMethod $method): bool => $method->getName() === '__construct');

        if ($publicMethods->contains(fn (ReflectionMethod $method): bool => ! $method->isStatic())) {
            continue;
        }

        if ($publicMethods->isEmpty()) {
            continue;
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null || ! $constructor->isPrivate()) {
            $offenders[] = $fqcn;
        }
    }

    expect($offenders)->toBe([]);
});

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
        // by the same rule, beside the formatter that writes it.
        'App\Domain',
        'App\Logging',
        // Named for the artisan command each registers (`payouts:run`,
        // `orders:sweep`, `seed:activity`, `mcp:key`), not suffixed `Command`.
        'App\Console\Commands\RunWeeklyPayouts',
        'App\Console\Commands\SweepOrders',
        'App\Console\Commands\SeedActivity',
        'App\Console\Commands\MintMcpKey',
        // A delivery channel is not a notification, and Laravel's own docs
        // home for a custom channel is App\Notifications\Channels.
        'App\Notifications\Channels',
        // The storefront's abstract form request carries the visitor its
        // children validate against; the rules belong to the children.
        'App\Http\Requests\Shop\ShopRequest',
    ]);

arch()->preset()->security();
