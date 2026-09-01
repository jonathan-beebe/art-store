<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Notifications\NotificationMessage;

it('goes to the in-app inbox by default', function (): void {
    expect((new MessageReceived('Blue Vase', null))->via($this->seller()))->toBe(['database']);
});

it('follows the channels the config names', function (): void {
    config(['notifications.channels' => ['database', 'mail']]);

    expect((new MessageReceived('Blue Vase', null))->via($this->seller()))->toBe(['database', 'mail']);
});

it('stores the subject, body, and url of the message', function (): void {
    expect((new MessageReceived('Blue Vase', 'https://example.test/messages/1'))->toArray($this->seller()))
        ->toBe(NotificationMessage::messageReceived('Blue Vase', 'https://example.test/messages/1')->toArray());
});

it('mails a link to the thread when one is known', function (): void {
    $mail = (new MessageReceived('Blue Vase', 'https://example.test/messages/1'))->toMail($this->seller());

    expect($mail->subject)->toBe('New message')
        ->and($mail->introLines)->toBe(['You have a new message about Blue Vase.'])
        ->and($mail->actionUrl)->toBe('https://example.test/messages/1');
});

it('mails no link when the thread has no route yet', function (): void {
    $mail = (new MessageReceived('Blue Vase', null))->toMail($this->seller());

    expect($mail->actionUrl)->toBeNull();
});
