<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Logging\StoryEvent;
use App\Support\DataRedaction;
use App\Support\DbActivity;
use App\Support\IdMint;
use App\Support\RequestMarks;
use App\Support\Story;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The two lines that open and close every request, and the id that ties them
 * to each other and to the response the caller holds.
 *
 * This is the outermost middleware in the application rather than one of the
 * `web` group's, because a request that matches no route and one the forgery
 * guard refuses never reach a group, and a 404 is the line an operator goes
 * looking for first. Both come back through here as an ordinary response:
 * the framework's pipeline renders an exception into one at the stage that
 * raised it.
 *
 * What running this early costs is the session and the guards, which start
 * further in. `NameRequestVisitor` adds the session and actor marks from
 * inside the `web` group, so every line after it carries them.
 *
 * `request_id` goes back to the caller in `X-Request-Id`, so a person
 * reporting a broken page can hand over the one value that finds it.
 *
 * Every response this application returns finishes synchronously within
 * `handle()` — nothing streams — so the story's `will`/`did` pair is written
 * in one pass; there is no `terminate()` half waiting on a held connection.
 */
final readonly class LogRequestStory
{
    public const string REQUEST_ID_HEADER = 'X-Request-Id';

    /**
     * The response to a request that threw is built by the exception handler
     * and never passes back through this middleware, so the id it must carry
     * travels on the request instead. bootstrap/app.php reads it there, and
     * {@see \App\Analytics\RequestFacts} reads the same constant off
     * {@see RequestMarks} rather than off this class, since nothing outside
     * `App\Http` may depend on a middleware directly.
     */
    public const string REQUEST_ID_ATTRIBUTE = RequestMarks::REQUEST_ID_ATTRIBUTE;

    private const string REQUEST_ID_PREFIX = 'req';

    /**
     * A caller's own request id is honoured only in this shape: it is echoed
     * in a header and written into a log line, and neither should carry
     * whatever else a caller might send. `\A` and `\z` rather than `^` and
     * `$`, which would admit a value with a newline on the end of it.
     */
    private const string GIVEN_REQUEST_ID = '/\A[A-Za-z0-9_-]{1,64}\z/';

    /**
     * The one path in this application whose URL is itself a credential. The
     * router has resolved nothing this early, so the path is matched rather
     * than the route; the sidecar test holds the two together.
     */
    private const string TOKEN_PATH = '/auth/magic/';

    private const string TOKEN_MARK = '{token}';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Story::forget();
        Log::withoutContext();
        DbActivity::reset();

        $requestId = $this->requestId($request);
        $request->attributes->set(self::REQUEST_ID_ATTRIBUTE, $requestId);
        Story::follows($requestId);

        $path = $this->path($request);
        $story = Story::for(StoryEvent::HttpRequest)->will("{$request->method()} {$path}", array_filter([
            'method' => $request->method(),
            'path' => $path,
            // §2.1's redaction rule applies to the query string the same as
            // every other `data` field (§2.2) — a visitor's own typing, not
            // a fact the story wrote for itself.
            'query' => DataRedaction::redact($request->query()),
        ]));

        try {
            $response = $next($request);
        } catch (Throwable $error) {
            $story->failed($error, "{$request->method()} {$path} broke");

            throw $error;
        }

        $response->headers->set(self::REQUEST_ID_HEADER, $requestId);

        $status = $response->getStatusCode();
        $story->did("{$request->method()} {$path} {$status}", [
            'status' => $status,
            'db' => DbActivity::snapshot(),
        ]);

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
     * The path as asked for, with one substitution: a magic-link token is the
     * credential itself, so the segment carrying it never reaches a log line.
     */
    private function path(Request $request): string
    {
        $path = $request->getPathInfo();

        return str_starts_with($path, self::TOKEN_PATH) ? self::TOKEN_PATH.self::TOKEN_MARK : $path;
    }
}
