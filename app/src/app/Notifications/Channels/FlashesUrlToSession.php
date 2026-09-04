<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

/**
 * A notification the session-flash channel can deliver: it hands over the one
 * URL the debug alert renders.
 */
interface FlashesUrlToSession
{
    public function toSessionFlash(object $notifiable): string;
}
