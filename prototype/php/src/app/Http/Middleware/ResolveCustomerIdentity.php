<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Customers\ResolveCustomerFromCookie;
use App\Models\Customer;
use App\Support\CustomerIdentity;
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

        return $next($request);
    }
}
