<?php

declare(strict_types=1);

use App\Domain\Money\Money;
use Illuminate\Database\Eloquent\Model;
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
    '../app/Events',
    '../app/Http/Controllers/Admin',
    '../app/Http/Controllers/Seller',
    '../app/Http/Requests/Admin',
    '../app/Http/Requests/Seller',
    '../app/Listeners',
    '../app/Models',
    '../app/Notifications',
    '../app/Observers',
    '../app/Policies',
    '../app/Providers',
    '../app/Support',
    '../app/View/Components',
);

pest()->extend(Tests\StorefrontTestCase::class)->in(
    '../app/Http/Controllers/Shop',
    '../app/Http/Requests/Shop',
    '../app/View/Composers',
    'SmokeTest.php',
    'ConfiguratorSmokeTest.php',
);

pest()->extend(Tests\TestCase::class)->in('../app/Analytics', '../app/Logging', '../routes', 'DatabaseConfigTest.php');

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
    expect($this->value)->toBeInstanceOf(Money::class);

    /** @var Money $money */
    $money = $this->value;

    return expect($money->cents)->toBe($cents);
});

expect()->extend('toHaveStatus', function (UnitEnum $status) {
    expect($this->value)->toBeInstanceOf(Model::class);

    /** @var Model $model */
    $model = $this->value;

    return expect($model->fresh()?->getAttribute('status'))->toBe($status);
});
