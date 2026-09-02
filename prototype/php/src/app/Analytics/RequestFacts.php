<?php

declare(strict_types=1);

namespace App\Analytics;

use App\Support\RequestMarks;
use Illuminate\Http\Request;
use Throwable;

/**
 * The ip, session, and request id of the request behind one analytics
 * event. {@see Analytics::recordEvent()} reads one fresh per event, rather
 * than once for the process, so a long-buffered event never carries a
 * later request's facts. All three are null together for anything that is
 * not a real HTTP request: a seeder, an artisan command, the synthetic
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

        $sessionId = $request->cookie(RequestMarks::SESSION_COOKIE);

        return self::of(
            $request->ip(),
            is_string($sessionId) ? $sessionId : null,
            $requestId,
        );
    }
}
