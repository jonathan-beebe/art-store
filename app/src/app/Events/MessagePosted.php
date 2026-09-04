<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Message;
use DateTimeImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A message was appended to a thread.
 */
final readonly class MessagePosted
{
    use Dispatchable;

    public function __construct(public Message $message, public DateTimeImmutable $sentAt) {}
}
