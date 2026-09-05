<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Customers\ResolveCustomerFromCookie;
use App\Domain\Auth\ActorType;
use App\Logging\Story;
use App\Models\Customer;
use App\Shop\CustomerIdentity;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final readonly class ResolveCustomerIdentity
{
    public function __construct(private ResolveCustomerFromCookie $resolveFromCookie) {}

    /**
     * Every storefront request has a customer behind it, so favorites, carts,
     * and orders have somewhere to hang before anyone signs in. That customer
     * is not always a row yet: a browser signed in, or already carrying a
     * cookie, gets the row it names; a browser arriving with neither gets an
     * unsaved one, and stays unsaved unless the request writes something
     * under it (`ShopController::knownVisitor()`) — a crawler, a scanner, or
     * one bounced visit mints no row and sets no cookie.
     *
     * This is an aliased middleware, so the route it guards decides what ran
     * ahead of it. On the storefront `NameRequestVisitor` has already asked
     * the cookie who the visitor is and this reads that answer off the
     * request; on a route outside the `web` group this is the first to ask.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $customer = Auth::guard('customer')->user()
            ?? CustomerIdentity::fromCookie($request, $this->resolveFromCookie)
            ?? new Customer;

        CustomerIdentity::attachTo($request, $customer);

        if ($customer->exists) {
            CustomerIdentity::rememberInCookie($customer);
            Story::actorIs(ActorType::Customer, (string) $customer->id);
        }

        return $next($request);
    }
}
