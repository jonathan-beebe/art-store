<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Tests are sidecars: every *Test.php file sits beside the production file
| it covers, so the base class a file needs depends on where it lives
| rather than on a tests/Feature or tests/Unit split. Each binding below
| matches the base class every test file under it already declares with
| `extends`, so Pest and PHPUnit agree on which class runs the file.
|
*/

pest()->extend(Tests\CommerceTestCase::class)->in(
    '../app/Actions',
    '../app/Console/Commands',
    '../app/Http/Controllers/Seller',
    '../app/Http/Requests/Seller',
    '../app/Models/ListingTest.php',
    '../app/Policies',
);

pest()->extend(Tests\StorefrontTestCase::class)->in(
    '../app/Http/Controllers/Shop',
    '../app/Http/Requests/Shop',
    'SmokeTest.php',
);

pest()->extend(Tests\TestCase::class)->use(RefreshDatabase::class)->in(
    '../app/Http/Controllers/Auth',
    '../app/Http/Middleware',
    '../app/Http/Requests/Auth',
    '../database/seeders',
);

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeMoney', function (int $cents) {
    return expect($this->value)->toBeInstanceOf(App\Domain\Money\Money::class)
        ->and($this->value->cents)->toBe($cents);
});

expect()->extend('toHaveStatus', function (UnitEnum $status) {
    return expect($this->value->fresh()?->status)->toBe($status);
});
