<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Support\UnreadCountStream;
use Generator;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The seller portal's live badge: an event stream scoped to the seller the
 * auth.seller guard resolved, never to anything the request carries.
 *
 * One open stream holds one `php artisan serve` worker for as long as the
 * tab stays open, up to UnreadCountStream::LIFETIME_SECONDS;
 * docker-compose.yml sizes PHP_CLI_SERVER_WORKERS with that cost in mind.
 */
final class EventsController extends SellerController
{
    public function __invoke(): StreamedResponse
    {
        $seller = $this->seller();
        $deadline = $this->now()->modify('+'.UnreadCountStream::LIFETIME_SECONDS.' seconds');

        return response()->eventStream(
            fn (): Generator => UnreadCountStream::forActor($seller, $deadline),
            endStreamWith: null,
        );
    }
}
