<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\RateLimiting\RateLimitExceeded;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * The shell's one clock. Domain code receives the instant it produces;
     * nothing under app/Domain reads a clock of its own.
     */
    protected function now(): DateTimeImmutable
    {
        return now()->toDateTimeImmutable();
    }

    /**
     * docs/spec.md §3's trip response for a route that has a form to
     * give back: the page re-renders at 429 with `Retry-After`, and the
     * sentence goes into `$errors` the same way every other page-level
     * refusal already does — field-less, since it names no key a `@error`
     * directive on this or any other form would match.
     *
     * @param  view-string  $view
     * @param  array<string, mixed>  $data
     */
    protected function tooManyRequests(RateLimitExceeded $exceeded, string $view, array $data = []): Response
    {
        $message = "Too many requests — try again in {$exceeded->retryAfterMinutes()} minutes.";

        // A view reads `$errors` as the `ViewErrorBag` the session-errors
        // middleware normally shares — a bare `MessageBag` answers `any()`
        // and `all()` the same way, but not `getBag()`, which a form the
        // validator has ever failed also calls. Shared rather than handed
        // to `view()` as data: the layout that renders the banner is a
        // component the page renders inside itself, and a component reads
        // the shared pool rather than its parent view's own variables.
        $errors = new ViewErrorBag;
        $errors->put('default', new MessageBag(['rate_limit' => [$message]]));
        ViewFacade::share('errors', $errors);

        /** @var View $rendered */
        $rendered = view($view, $data);

        return response($rendered, 429)->header('Retry-After', (string) $exceeded->retryAfterSeconds);
    }
}
