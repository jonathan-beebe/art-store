<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Messaging\ConversationKind;
use App\Models\Admin;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Listing;
use App\Models\ListingFaq;
use App\Models\Message;
use App\Models\Seller;

beforeEach(function (): void {
    $this->seed();
});

it('seeds one conversation of each kind, and a second listing question', function (): void {
    expect(Conversation::count())->toBe(5);

    foreach (ConversationKind::cases() as $kind) {
        $expected = $kind === ConversationKind::ListingQuestion ? 2 : 1;

        expect(Conversation::where('kind', $kind)->count())->toBe($expected);
    }
});

it('seeds ten messages with a read and unread spread', function (): void {
    expect(Message::count())->toBe(10)
        ->and(Message::whereNull('read_at')->count())->toBe(6)
        ->and(Message::whereNotNull('read_at')->count())->toBe(4);
});

it('publishes the seeded listings question as its one FAQ entry', function (): void {
    $listing = Listing::where('title', MessagingSeeder::FAQ_LISTING_TITLE)->firstOrFail();

    expect(ListingFaq::where('listing_id', $listing->id)->count())->toBe(1);

    $faq = ListingFaq::where('listing_id', $listing->id)->sole();
    expect($faq->question)->toBe('Does this vase come with a stand for display?')
        ->and($faq->answer)->toBe('Yes — it ships with a simple wood stand included.');

    $sybill = Seller::where('email', SellerSeeder::SYBILL_EMAIL)->firstOrFail();
    $source = Message::findOrFail($faq->source_message_id);

    expect($source->body)->toBe($faq->answer)
        ->and($source->sender_type)->toBe($sybill->getMorphClass())
        ->and($source->sender_id)->toBe($sybill->id);
});

it('leaves a non-zero unread count for the seeded seller, customer, and admin', function (): void {
    $sybill = Seller::where('email', SellerSeeder::SYBILL_EMAIL)->firstOrFail();
    $dean = Seller::where('email', SellerSeeder::DEAN_EMAIL)->firstOrFail();
    $hermione = Customer::where('email', CustomerSeeder::HERMIONE_EMAIL)->firstOrFail();
    $admin = Admin::where('email', AdminSeeder::ADMINS[0]['email'])->firstOrFail();

    expect(Message::query()->unreadInInboxOf($sybill)->count())->toBe(2)
        ->and(Message::query()->unreadInInboxOf($dean)->count())->toBe(1)
        ->and(Message::query()->unreadInInboxOf($hermione)->count())->toBe(1)
        ->and(Message::query()->unreadInInboxOf($admin)->count())->toBe(1);
});

it('changes nothing on a second run', function (): void {
    $this->seed(MessagingSeeder::class);

    expect(Conversation::count())->toBe(5)
        ->and(Message::count())->toBe(10)
        ->and(ListingFaq::count())->toBe(1);
});
