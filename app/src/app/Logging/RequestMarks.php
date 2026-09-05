<?php

declare(strict_types=1);

namespace App\Logging;

/**
 * The two marks a request carries outside the logger's context, named once
 * so a class that is not itself under `App\Http` — {@see \App\Analytics\RequestFacts},
 * today — can read them without depending on the middleware that stamps
 * them. `App\Http\Middleware\LogRequestStory::REQUEST_ID_ATTRIBUTE` and
 * `App\Http\Middleware\NameRequestVisitor::SESSION_COOKIE` alias these, so
 * every existing caller of either keeps working unchanged.
 */
final class RequestMarks
{
    /**
     * The `Illuminate\Http\Request` attribute `LogRequestStory` stamps with
     * the id it minted or honoured for the request — never present on the
     * synthetic request the console kernel binds for an artisan run.
     */
    public const string REQUEST_ID_ATTRIBUTE = 'story.request_id';

    /**
     * The cookie `NameRequestVisitor` mints on a browser's first response
     * and keeps unchanged after, whoever signs in or out.
     */
    public const string SESSION_COOKIE = 'sid';

    private function __construct() {} // @codeCoverageIgnore
}
