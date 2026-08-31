<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Logging\StoryEvent;
use App\Support\DataRedaction;
use App\Support\DbActivity;
use App\Support\IdMint;
use App\Support\Story;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
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
 */
final readonly class LogRequestStory
{
    public const string REQUEST_ID_HEADER = 'X-Request-Id';

    /**
     * The response to a request that threw is built by the exception handler
     * and never passes back through this middleware, so the id it must carry
     * travels on the request instead. bootstrap/app.php reads it there.
     */
    public const string REQUEST_ID_ATTRIBUTE = 'story.request_id';

    /**
     * A streamed response's `did` line waits for the stream to finish, which
     * is after this middleware has already been re-resolved for `terminate()`
     * — so the open story travels on the request instead of an instance
     * property, the same way REQUEST_ID_ATTRIBUTE does.
     */
    private const string OPEN_STORY_ATTRIBUTE = 'story.open';

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

        if ($response instanceof StreamedResponse) {
            // Left at its default, PHP kills the script on the write that
            // first fails after the client is gone — every line after it,
            // this middleware's terminate() included, never runs. This opts
            // out, so the stream's own connection_aborted() check is what
            // decides when to stop.
            ignore_user_abort(true);

            $request->attributes->set(self::OPEN_STORY_ATTRIBUTE, $story);

            return $response;
        }

        $status = $response->getStatusCode();
        $story->did("{$request->method()} {$path} {$status}", [
            'status' => $status,
            'db' => DbActivity::snapshot(),
        ]);

        return $response;
    }

    /**
     * The closing half of a streamed response's story: `handle()` returns
     * before the stream body has run, so the `did` line that covers the held
     * connection is written here instead, once the framework calls this
     * after the response has finished sending. A request that never opened a
     * stream stashed nothing, so there is nothing to close.
     */
    public function terminate(Request $request, Response $response): void
    {
        $story = $request->attributes->get(self::OPEN_STORY_ATTRIBUTE);

        if (! $story instanceof Story) {
            return;
        }

        $path = $this->path($request);
        $status = $response->getStatusCode();

        // PHP learns a stream's client is gone only from a failed write, so
        // the eventStream loop this closes has already broken out by the
        // time the abort is visible here.
        $story->did("{$request->method()} {$path} {$status}", [
            'status' => $status,
            'db' => DbActivity::snapshot(),
            'disconnected' => connection_aborted() === 1 ? true : null,
        ]);
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
