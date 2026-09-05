<?php

declare(strict_types=1);

namespace App\Analytics;

use App\Support\RequestMarks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Throwable;

/**
 * The ip, session, and request id of the request behind one analytics
 * event. {@see Analytics::recordEvent()} reads one fresh per event, so a
 * long-buffered event never carries a later request's facts. All three
 * are null together for anything that is not a real HTTP request: a
 * seeder, an artisan command, the synthetic
 * request the console kernel binds for one — none of them ever reach
 * `App\Http\Middleware\LogRequestStory`, so none of them carry the id it
 * stamps under {@see RequestMarks::REQUEST_ID_ATTRIBUTE}.
 */
final readonly class RequestFacts
{
    private function __construct(
        public ?string $ip,
        public ?string $sessionId,
        public ?string $requestId,
    ) {}

    /**
     * Every field named directly, for a test that wants one without a real
     * request behind it.
     */
    public static function of(?string $ip, ?string $sessionId, ?string $requestId): self
    {
        return new self($ip, $sessionId, $requestId);
    }

    /**
     * The facts for whichever request the application container holds
     * right now.
     */
    public static function current(): self
    {
        try {
            $request = app(Request::class);
        } catch (Throwable) {
            return self::of(null, null, null);
        }

        $requestId = $request->attributes->get(RequestMarks::REQUEST_ID_ATTRIBUTE);

        // The one signal that tells a real request from the console
        // kernel's synthetic one: only LogRequestStory, the outermost
        // middleware on the real pipeline, stamps this attribute.
        if (! is_string($requestId)) {
            return self::of(null, null, null);
        }

        return self::of(
            $request->ip(),
            self::sessionId($request),
            $requestId,
        );
    }

    /**
     * A returning browser's `sid` rides in on the request. A browser's
     * first request has none yet — `NameRequestVisitor` mints one and
     * queues it on the response without rewriting the request — so this
     * falls back to that queued cookie's value.
     */
    private static function sessionId(Request $request): ?string
    {
        $held = $request->cookie(RequestMarks::SESSION_COOKIE);

        if (is_string($held)) {
            return $held;
        }

        return Cookie::queued(RequestMarks::SESSION_COOKIE)?->getValue();
    }
}
