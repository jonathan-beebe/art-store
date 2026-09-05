<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Customers\ResolveCustomerFromCookie;
use App\Domain\Auth\ActorType;
use App\Domain\Identifiers\PrefixedId;
use App\Support\CustomerIdentity;
use App\Support\IdMint;
use App\Support\RequestMarks;
use App\Support\Story;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Which browser is asking, and who is behind it. Both marks go into the
 * logger's context, where every line written for the rest of the request
 * picks them up.
 *
 * This runs inside the `web` group because both answers need the group: the
 * `sid` cookie is readable only after cookies are decrypted, and a guard can
 * only name a signed-in actor once the session has started. The request's own
 * opening line is written before that by `LogRequestStory`, which is what
 * lets a request that never reaches a group still be logged.
 *
 * `session_id` outlives the request in the `sid` cookie and survives sign-in
 * and sign-out, so a browser's visits join up whether or not anyone signed in.
 */
final readonly class NameRequestVisitor
{
    // {@see \App\Analytics\RequestFacts} reads the same cookie name off
    // RequestMarks. Nothing outside `App\Http` may depend on a middleware
    // directly.
    public const string SESSION_COOKIE = RequestMarks::SESSION_COOKIE;

    private const string SESSION_ID_PREFIX = 'ses';

    // A browser's visits are worth joining up across a year, the same span
    // the identity cookie keeps.
    private const int SESSION_COOKIE_LIFETIME_MINUTES = 60 * 24 * 365;

    public function __construct(private ResolveCustomerFromCookie $resolveFromCookie) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Story::inSession($this->sessionId($request));
        $this->nameActor($request);

        return $next($request);
    }

    /**
     * The `sid` cookie is minted on the first response a browser gets and
     * kept from then on. Nothing rewrites it: signing in and signing out
     * change who the actor is, not which browser is asking.
     */
    private function sessionId(Request $request): string
    {
        $held = $request->cookie(self::SESSION_COOKIE);

        if (is_string($held) && PrefixedId::parse(self::SESSION_ID_PREFIX, $held) !== null) {
            return $held;
        }

        $minted = IdMint::of(self::SESSION_ID_PREFIX);
        Cookie::queue(self::SESSION_COOKIE, $minted, self::SESSION_COOKIE_LIFETIME_MINUTES);

        return $minted;
    }

    /**
     * A signed-in seller, customer, or admin is named by their guard. A
     * storefront visitor who has never signed in is named by the identity
     * cookie, because an anonymous `cus_…` joins their lines together just as
     * well. Only a browser arriving for the very first time has no actor
     * here, and the identity middleware names the row it creates for them.
     */
    private function nameActor(Request $request): void
    {
        foreach ([ActorType::Seller, ActorType::Admin, ActorType::Customer] as $actorType) {
            $signedIn = Auth::guard($actorType->guard())->id();

            if (is_scalar($signedIn)) {
                Story::actorIs($actorType, (string) $signedIn);

                return;
            }
        }

        $customer = CustomerIdentity::fromCookie($request, $this->resolveFromCookie);

        if ($customer !== null) {
            Story::actorIs(ActorType::Customer, (string) $customer->id);
        }
    }
}
