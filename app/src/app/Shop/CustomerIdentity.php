<?php

declare(strict_types=1);

namespace App\Shop;

use App\Analytics\Analytics;
use App\Analytics\RequestFacts;
use App\Domain\Auth\ActorType;
use App\Logging\Story;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Carries the storefront visitor's identity: the encrypted cookie that
 * survives between visits, and the request attribute that `current()` reads
 * during one. A visitor nothing has resolved yet holds an unsaved row until
 * `commit()` gives it one — see `App\Http\Controllers\Shop\ShopController::knownVisitor()`.
 */
final class CustomerIdentity
{
    public const COOKIE = 'customer_id';

    private const REQUEST_ATTRIBUTE = 'customer';

    private const RESOLVED_ATTRIBUTE = 'customer.from_cookie';

    // A browsing history is worth more than a session, so the cookie outlives one.
    private const COOKIE_LIFETIME_MINUTES = 60 * 24 * 365;

    private function __construct() {} // @codeCoverageIgnore

    public static function cookieValue(Request $request): ?string
    {
        $value = $request->cookie(self::COOKIE);

        return is_string($value) ? $value : null;
    }

    /**
     * The customer the cookie names, read from the database the first time a
     * request asks and off the request itself after that. Two middlewares ask
     * — one to name the request's actor, one to bind the visitor the
     * controllers read — and the cookie cannot answer them differently.
     *
     * The attribute is written even when the cookie names nobody, so a cookie
     * pointing at a customer that no longer exists is looked up once for the
     * request and reused for every asker after.
     *
     * `$resolve` turns the cookie's value into the customer it names. The two
     * middlewares hand in `ResolveCustomerFromCookie`, which follows the
     * merge chain; this class knows only the callable's shape.
     *
     * @param  callable(?string): ?Customer  $resolve
     */
    public static function fromCookie(Request $request, callable $resolve): ?Customer
    {
        if (! $request->attributes->has(self::RESOLVED_ATTRIBUTE)) {
            $request->attributes->set(self::RESOLVED_ATTRIBUTE, $resolve(self::cookieValue($request)));
        }

        $resolved = $request->attributes->get(self::RESOLVED_ATTRIBUTE);

        return $resolved instanceof Customer ? $resolved : null;
    }

    /**
     * Turns an unsaved visitor into a row worth remembering: saved, put in
     * the cookie, and named as the request's actor. A page that only reads
     * never calls this, so a crawler or a browser with cookies off that
     * never triggers a write leaves no row behind. A row already saved —
     * signed in, or resolved from an earlier visit's cookie — comes back
     * unchanged; committing it twice in one request costs nothing.
     *
     * Also claims the session's `analytics_visits` row for this customer
     * (`Analytics::claimVisit()`), so a session whose first-touch page
     * mints no row still ends up with its landing page and channel
     * credited to the customer it becomes, once one exists. A request
     * carrying no session id — the console kernel's synthetic request —
     * claims nothing.
     */
    public static function commit(Customer $visitor): Customer
    {
        if ($visitor->exists) {
            return $visitor;
        }

        $visitor->save();
        self::rememberInCookie($visitor);
        Story::actorIs(ActorType::Customer, (string) $visitor->id);

        $sessionId = RequestFacts::current()->sessionId;

        if ($sessionId !== null) {
            app(Analytics::class)->claimVisit($sessionId, $visitor->id);
        }

        return $visitor;
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
