<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Support\UnreadCountStream;
use Generator;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The admin site's live badge: an event stream scoped to the admin the
 * auth.admin guard resolved, never to anything the request carries.
 *
 * One open stream holds one `php artisan serve` worker for as long as the
 * tab stays open, up to UnreadCountStream::LIFETIME_SECONDS;
 * docker-compose.yml sizes PHP_CLI_SERVER_WORKERS with that cost in mind.
 */
final class EventsController extends AdminController
{
    public function __invoke(): StreamedResponse
    {
        $admin = $this->admin();
        $deadline = $this->now()->modify('+'.UnreadCountStream::LIFETIME_SECONDS.' seconds');

        return response()->eventStream(
            fn (): Generator => UnreadCountStream::forActor($admin, $deadline),
            endStreamWith: null,
        );
    }
}
