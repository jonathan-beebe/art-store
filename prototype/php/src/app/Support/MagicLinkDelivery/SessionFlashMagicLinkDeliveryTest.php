<?php

declare(strict_types=1);

namespace App\Support\MagicLinkDelivery;

use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;

it('flashes the url under the debug alert key', function (): void {
    $session = new Store('art-store', new ArraySessionHandler(60));

    (new SessionFlashMagicLinkDelivery($session))->deliver('artist@example.com', 'http://localhost:8000/auth/magic/abc');

    expect($session->get('debug_magic_link'))->toBe('http://localhost:8000/auth/magic/abc');
});
