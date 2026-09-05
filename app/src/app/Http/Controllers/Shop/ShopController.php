<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Shop\CustomerIdentity;
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
     * The visitor, guaranteed a row: unsaved becomes saved, the cookie is
     * queued, and the actor is named. A page that only reads calls
     * `visitor()`, tolerating one that is not saved yet; a request that
     * writes a row for the visitor, or records an analytics event under
     * their id (a listing view, a store view, a cart add, a favorite, an
     * order), calls this first so the write has a real id to hang on.
     */
    protected function knownVisitor(): Customer
    {
        return CustomerIdentity::commit($this->visitor());
    }

    /**
     * Middleware resolves the visitor; no auth guard tracks them. Every
     * storefront gate check passes the visitor to `Gate::forUser()`
     * explicitly.
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
