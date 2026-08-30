<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\CustomerIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * Shared ground for the storefront pages: the visitor behind the request, and
 * the gate every page authorizes against.
 */
abstract class ShopController extends Controller
{
    protected function visitor(): Customer
    {
        return CustomerIdentity::current() ?? throw new RuntimeException('The storefront runs behind the customer.identity middleware.');
    }

    /**
     * The visitor is resolved by middleware rather than signed in on a guard,
     * so every storefront gate check names them instead of reading one.
     */
    protected function authorizeVisitor(string $ability, mixed $subject): void
    {
        Gate::forUser($this->visitor())->authorize($ability, $subject);
    }

    /**
     * A query string value read as a plain string, or null for anything
     * else it could be (absent, an array, a nested query) — the shape every
     * storefront filter (`q`, `medium`) takes as input.
     */
    protected function submitted(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        return is_string($value) ? $value : null;
    }
}
