<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Domain\Money\Money;
use App\Notifications\ItemSold;
use App\Notifications\MagicLinkIssued;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use InvalidArgumentException;

it('flashes the url under the debug alert key', function (): void {
    $session = new Store('art-store', new ArraySessionHandler(60));
    $url = 'http://localhost:8000/auth/magic/abc';

    (new SessionFlashChannel($session))->send(new AnonymousNotifiable, new MagicLinkIssued($url));

    expect($session->get(SessionFlashChannel::KEY))->toBe($url);
});

it('refuses a notification that carries no url', function (): void {
    $channel = new SessionFlashChannel(new Store('art-store', new ArraySessionHandler(60)));

    expect(fn () => $channel->send(new AnonymousNotifiable, new ItemSold('ord_00000000000000000000000004', Money::fromCents(9000))))
        ->toThrow(InvalidArgumentException::class, 'carries no URL to flash to the session.');
});
