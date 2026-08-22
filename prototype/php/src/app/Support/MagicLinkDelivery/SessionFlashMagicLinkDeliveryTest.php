<?php

namespace App\Support\MagicLinkDelivery;

use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use PHPUnit\Framework\TestCase;

final class SessionFlashMagicLinkDeliveryTest extends TestCase
{
    public function test_it_flashes_the_url_under_the_debug_alert_key(): void
    {
        $session = new Store('art-store', new ArraySessionHandler(60));

        (new SessionFlashMagicLinkDelivery($session))->deliver('artist@example.com', 'http://localhost:8000/auth/magic/abc');

        $this->assertSame('http://localhost:8000/auth/magic/abc', $session->get('debug_magic_link'));
    }
}
