<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Domain\Messaging\MessageBody;
use App\Domain\Messaging\ThreadOpening;
use App\Domain\Messaging\ThreadTitle;
use App\Models\Admin;
use App\Models\Conversation;
use App\Models\CustomerBlock;
use App\Models\Message;
use Illuminate\Auth\Access\AuthorizationException;

it('opens the thread and posts the message that opens it', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($seller);
    $opening = ThreadOpening::listingQuestion($seller->id, $customer->id, $listing->id, ThreadTitle::fromBody('Does this ship framed?'));

    $conversation = app(OpenThread::class)($opening, $customer, MessageBody::of('Does this ship framed?'), $this->moment('2026-08-20 10:00:00'));

    expect($conversation->seller_id)->toBe($seller->id)
        ->and($conversation->customer_id)->toBe($customer->id)
        ->and($conversation->listing_id)->toBe($listing->id)
        ->and($conversation->title)->toBe('Does this ship framed?')
        ->and(Message::sole()->body)->toBe('Does this ship framed?')
        ->and($conversation->last_message_at?->format('Y-m-d H:i:s'))->toBe('2026-08-20 10:00:00');
});

it('leaves no thread behind when the gate turns the sender down', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    CustomerBlock::factory()->create(['customer_id' => $customer->id]);
    $listing = $this->listing($seller);
    $opening = ThreadOpening::listingQuestion($seller->id, $customer->id, $listing->id, ThreadTitle::fromBody('Still available?'));

    $ask = fn () => app(OpenThread::class)($opening, $customer, MessageBody::of('Still available?'), $this->moment('2026-08-20 10:00:00'));

    expect($ask)->toThrow(AuthorizationException::class)
        ->and(Conversation::count())->toBe(0)
        ->and(Message::count())->toBe(0);
});

it('opens a fresh thread rather than finding one for a repeated ask', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($seller);
    $opening = fn (string $body) => ThreadOpening::listingQuestion($seller->id, $customer->id, $listing->id, ThreadTitle::fromBody($body));
    $ask = app(OpenThread::class);

    $first = $ask($opening('Does this ship framed?'), $customer, MessageBody::of('Does this ship framed?'), $this->moment('2026-08-20 10:00:00'));
    $second = $ask($opening('And is it signed?'), $customer, MessageBody::of('And is it signed?'), $this->moment('2026-08-20 11:00:00'));

    expect($second->id)->not->toBe($first->id)
        ->and(Conversation::count())->toBe(2)
        ->and(Message::count())->toBe(2);
});

it('opens an admin thread with a seller, admin_id set by the opener\'s own first reply', function (): void {
    $admin = Admin::factory()->create();
    $seller = $this->seller();
    $opening = ThreadOpening::adminSeller($seller->id, ThreadTitle::of('Payout timing'));

    $conversation = app(OpenThread::class)($opening, $admin, MessageBody::of('Your payout ran this morning.'), $this->moment('2026-08-20 10:00:00'));

    expect($conversation->admin_id)->toBe($admin->id)
        ->and($conversation->seller_id)->toBe($seller->id)
        ->and($conversation->title)->toBe('Payout timing')
        ->and(Message::sole()->sender_type)->toBe('admin');
});
