<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Channels\SessionFlashChannel;
use Illuminate\Notifications\AnonymousNotifiable;
use InvalidArgumentException;

const MAGIC_LINK_URL = 'http://localhost:8000/auth/magic/abc';

it('goes to the channel the config names', function (string $delivery, string $channel): void {
    config(['magic_links.delivery' => $delivery]);

    expect(MagicLinkIssued::channel())->toBe($channel)
        ->and((new MagicLinkIssued(MAGIC_LINK_URL))->via(new AnonymousNotifiable))->toBe([$channel]);
})->with([
    'session' => ['session', SessionFlashChannel::class],
    'mail' => ['mail', 'mail'],
]);

it('refuses a delivery channel it does not know', function (): void {
    config(['magic_links.delivery' => 'carrier pigeon']);

    expect(MagicLinkIssued::channel(...))
        ->toThrow(InvalidArgumentException::class, 'Unknown magic link delivery [carrier pigeon].');
});

it('hands the session channel the link', function (): void {
    expect((new MagicLinkIssued(MAGIC_LINK_URL))->toSessionFlash(new AnonymousNotifiable))->toBe(MAGIC_LINK_URL);
});

it('mails the link with the window it stays usable', function (): void {
    config(['magic_links.expiry_minutes' => 15]);

    $mail = (new MagicLinkIssued(MAGIC_LINK_URL))->toMail(new AnonymousNotifiable);

    expect($mail->subject)->toBe('Your sign-in link')
        ->and($mail->introLines)->toBe(['Follow this link to sign in. It expires in 15 minutes.'])
        ->and($mail->actionUrl)->toBe(MAGIC_LINK_URL);
});
