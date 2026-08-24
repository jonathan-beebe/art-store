<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Customers\ResolveCustomerFromCookie;
use App\Domain\Auth\ActorType;
use App\Domain\Identifiers\PrefixedId;
use App\Logging\StoryEvent;
use App\Support\CustomerIdentity;
use App\Support\IdMint;
use App\Support\Story;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The marks that join one request's log lines to each other, and the two
 * lines that open and close it.
 *
 * `request_id` ties the lines of a single request together and goes back to
 * the caller in `X-Request-Id`, so a person reporting a broken page can hand
 * over the one value that finds it. `session_id` outlives the request in the
 * `sid` cookie and survives sign-in and sign-out, so a browser's visits join
 * up whether or not anyone signed in. Both go into the logger's context,
 * where every line written for the rest of the request picks them up.
 */
final readonly class LogRequestStory
{
    public const string REQUEST_ID_HEADER = 'X-Request-Id';

    public const string SESSION_COOKIE = 'sid';

    private const string REQUEST_ID_PREFIX = 'req';

    private const string SESSION_ID_PREFIX = 'ses';

    /**
     * A caller's own request id is honoured only in this shape: it is echoed
     * in a header and written into a log line, and neither should carry
     * whatever else a caller might send.
     */
    private const string GIVEN_REQUEST_ID = '/^[A-Za-z0-9_-]{1,64}$/';

    // A browser's visits are worth joining up across a year, the same span
    // the identity cookie keeps.
    private const int SESSION_COOKIE_LIFETIME_MINUTES = 60 * 24 * 365;

    public function __construct(private ResolveCustomerFromCookie $resolveFromCookie) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Story::forget();
        Log::withoutContext();

        $requestId = $this->requestId($request);
        Story::follows($requestId, $this->sessionId($request));
        $this->nameActor($request);

        $path = $this->path($request);
        $story = Story::for(StoryEvent::HttpRequest)->will("{$request->method()} {$path}", [
            'method' => $request->method(),
            'path' => $path,
        ]);

        try {
            $response = $next($request);
        } catch (Throwable $error) {
            $story->failed($error, "{$request->method()} {$path} broke");

            throw $error;
        }

        $response->headers->set(self::REQUEST_ID_HEADER, $requestId);

        $status = $response->getStatusCode();
        $story->did("{$request->method()} {$path} {$status}", ['status' => $status]);

        return $response;
    }

    private function requestId(Request $request): string
    {
        $given = $request->headers->get(self::REQUEST_ID_HEADER);

        return is_string($given) && preg_match(self::GIVEN_REQUEST_ID, $given) === 1
            ? $given
            : IdMint::of(self::REQUEST_ID_PREFIX);
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

        $customer = ($this->resolveFromCookie)(CustomerIdentity::cookieValue($request));

        if ($customer !== null) {
            Story::actorIs(ActorType::Customer, (string) $customer->id);
        }
    }

    /**
     * The path as asked for, with one substitution: a magic-link token is the
     * credential itself, so the route parameter carrying it never reaches a
     * log line.
     */
    private function path(Request $request): string
    {
        $path = $request->getPathInfo();
        $token = $request->route('token');

        return is_string($token) ? str_replace($token, '{token}', $path) : $path;
    }
}
