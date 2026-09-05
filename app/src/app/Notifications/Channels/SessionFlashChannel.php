<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use Illuminate\Contracts\Session\Session;
use Illuminate\Notifications\Notification;
use InvalidArgumentException;

/**
 * Delivers a link by flashing it to the session, where the debug-alert partial
 * in both layouts renders it. This is how the app hands over a magic
 * link with no mailbox.
 */
final readonly class SessionFlashChannel
{
    public const KEY = 'debug_magic_link';

    public function __construct(private Session $session) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof FlashesUrlToSession) {
            throw new InvalidArgumentException($notification::class.' carries no URL to flash to the session.');
        }

        $this->session->flash(self::KEY, $notification->toSessionFlash($notifiable));
    }
}
