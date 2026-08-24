<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Customers\ResolveCustomerFromCookie;
use App\Domain\Auth\ActorType;
use App\Models\Customer;
use App\Support\CustomerIdentity;
use App\Support\Story;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final readonly class ResolveCustomerIdentity
{
    public function __construct(private ResolveCustomerFromCookie $resolveFromCookie) {}

    /**
     * Every storefront request has a customer behind it, so favorites, carts,
     * and orders have somewhere to hang before anyone signs in.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $customer = Auth::guard('customer')->user()
            ?? ($this->resolveFromCookie)(CustomerIdentity::cookieValue($request))
            ?? Customer::create([]);

        CustomerIdentity::attachTo($request, $customer);
        CustomerIdentity::rememberInCookie($customer);

        // A browser arriving for the first time had no id to be named by when
        // the request opened; from here every line it writes names the row it
        // was just given.
        Story::actorIs(ActorType::Customer, (string) $customer->id);

        return $next($request);
    }
}
