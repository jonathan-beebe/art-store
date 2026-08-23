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

it('seeds one conversation of each kind', function (): void {
    expect(Conversation::count())->toBe(4);

    foreach (ConversationKind::cases() as $kind) {
        expect(Conversation::where('kind', $kind)->count())->toBe(1);
    }
});

it('seeds roughly eleven messages with a read and unread spread', function (): void {
    expect(Message::count())->toBe(11)
        ->and(Message::whereNull('read_at')->count())->toBeGreaterThan(0)
        ->and(Message::whereNotNull('read_at')->count())->toBeGreaterThan(0);
});

it('publishes the seeded listings question as its one FAQ entry', function (): void {
    $listing = Listing::where('title', MessagingSeeder::FAQ_LISTING_TITLE)->firstOrFail();

    expect(ListingFaq::where('listing_id', $listing->id)->count())->toBe(1);

    $faq = ListingFaq::where('listing_id', $listing->id)->sole();
    expect($faq->question)->toBe('Does this vase come with a stand for display?')
        ->and($faq->answer)->toBe('Yes — it ships with a simple wood stand included.');
});

it('leaves a non-zero unread count for the seeded seller, customer, and admin', function (): void {
    $priya = Seller::where('email', SellerSeeder::PRIYA_EMAIL)->firstOrFail();
    $casey = Customer::where('email', CustomerSeeder::CASEY_EMAIL)->firstOrFail();
    $admin = Admin::where('email', AdminSeeder::EMAIL)->firstOrFail();

    expect(Message::query()->unreadInInboxOf($priya)->count())->toBeGreaterThan(0)
        ->and(Message::query()->unreadInInboxOf($casey)->count())->toBeGreaterThan(0)
        ->and(Message::query()->unreadInInboxOf($admin)->count())->toBeGreaterThan(0);
});

it('changes nothing on a second run', function (): void {
    $this->seed(MessagingSeeder::class);

    expect(Conversation::count())->toBe(4)
        ->and(Message::count())->toBe(11)
        ->and(ListingFaq::count())->toBe(1);
});
