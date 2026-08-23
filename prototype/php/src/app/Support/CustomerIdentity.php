<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Carries the storefront visitor's identity: the encrypted cookie that
 * survives between visits, and the request attribute that the `customer()`
 * helper reads during one.
 */
final class CustomerIdentity
{
    public const COOKIE = 'customer_id';

    private const REQUEST_ATTRIBUTE = 'customer';

    // A browsing history is worth more than a session, so the cookie outlives one.
    private const COOKIE_LIFETIME_MINUTES = 60 * 24 * 365;

    public static function cookieValue(Request $request): ?string
    {
        $value = $request->cookie(self::COOKIE);

        return is_string($value) ? $value : null;
    }

    public static function rememberInCookie(Customer $customer): void
    {
        Cookie::queue(self::COOKIE, (string) $customer->id, self::COOKIE_LIFETIME_MINUTES);
    }

    public static function forgetCookie(): void
    {
        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    public static function attachTo(Request $request, Customer $customer): void
    {
        $request->attributes->set(self::REQUEST_ATTRIBUTE, $customer);
    }

    /**
     * Null off the storefront, where no middleware has resolved an identity.
     */
    public static function current(): ?Customer
    {
        $customer = request()->attributes->get(self::REQUEST_ATTRIBUTE);

        return $customer instanceof Customer ? $customer : null;
    }
}
