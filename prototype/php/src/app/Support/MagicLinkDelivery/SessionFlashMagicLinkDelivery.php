<?php

declare(strict_types=1);

namespace App\Support\MagicLinkDelivery;

use Illuminate\Contracts\Session\Session;
use Override;

final readonly class SessionFlashMagicLinkDelivery implements MagicLinkDelivery
{
    public function __construct(private Session $session) {}

    /**
     * The debug-alert partial in both layouts renders whatever lands under
     * this key, which is how the prototype hands over a link with no mailbox.
     */
    #[Override]
    public function deliver(string $email, string $url): void
    {
        $this->session->flash('debug_magic_link', $url);
    }
}
