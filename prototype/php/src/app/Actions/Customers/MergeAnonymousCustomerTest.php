<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Domain\Money\Money;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerBlock;
use App\Models\CustomerMerge;
use App\Models\Message;
use App\Models\Seller;
use App\Notifications\ItemSold;
use App\Notifications\OrderShipped;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('records the merge so a stale cookie still resolves', function (): void {
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();

    app(MergeAnonymousCustomer::class)($anonymous, $verified);

    $this->assertDatabaseHas('customer_merges', [
        'anonymous_customer_id' => $anonymous->id,
        'customer_id' => $verified->id,
    ]);
});

it('merging the same anonymous customer twice writes one merge row', function (): void {
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();

    app(MergeAnonymousCustomer::class)($anonymous, $verified);
    app(MergeAnonymousCustomer::class)($anonymous, $verified);

    expect(CustomerMerge::count())->toBe(1);
});

it('returns the customer the history moved to', function (): void {
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();

    $merged = app(MergeAnonymousCustomer::class)($anonymous, $verified);

    expect($verified->is($merged))->toBeTrue();
});

it('leaves the anonymous row in place for the merge trail', function (): void {
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();

    app(MergeAnonymousCustomer::class)($anonymous, $verified);

    $this->assertDatabaseHas('customers', ['id' => $anonymous->id]);
});

it('re-points rows in a customer-owned table', function (): void {
    // The commerce tables carry columns this test knows nothing about, so the
    // table-driven re-pointing is proven against a row this test can write on its own.
    Schema::dropIfExists('favorites');
    Schema::create('favorites', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('customer_id');
    });
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();
    $bystander = Customer::factory()->create();
    DB::table('favorites')->insert([
        ['customer_id' => $anonymous->id],
        ['customer_id' => $bystander->id],
    ]);

    app(MergeAnonymousCustomer::class)($anonymous, $verified);

    expect(DB::table('favorites')->where('customer_id', $verified->id)->count())->toBe(1)
        ->and(DB::table('favorites')->where('customer_id', $anonymous->id)->count())->toBe(0)
        ->and(DB::table('favorites')->where('customer_id', $bystander->id)->count())->toBe(1);
});

it('skips a customer-owned table that does not exist', function (): void {
    Schema::dropIfExists('favorites');
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();

    app(MergeAnonymousCustomer::class)($anonymous, $verified);

    expect(CustomerMerge::count())->toBe(1);
});

it('re-points the notifications addressed to the anonymous customer', function (): void {
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();
    $bystander = Customer::factory()->create();
    $anonymous->notify(new OrderShipped(4, 'USPS', '94001'));
    $bystander->notify(new OrderShipped(5, 'USPS', '94002'));

    app(MergeAnonymousCustomer::class)($anonymous, $verified);

    expect($verified->notifications()->count())->toBe(1)
        ->and($anonymous->notifications()->count())->toBe(0)
        ->and($bystander->notifications()->count())->toBe(1);
});

it('leaves a seller notification where it is when a customer merges', function (): void {
    $anonymous = Customer::factory()->anonymous()->create();
    $seller = Seller::factory()->create();
    $seller->notify(new ItemSold(4, Money::fromCents(9000)));

    app(MergeAnonymousCustomer::class)($anonymous, Customer::factory()->create());

    expect($seller->notifications()->count())->toBe(1);
});

it('moves the anonymous customer\'s conversations to the verified customer', function (): void {
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();
    $conversation = Conversation::factory()->listingQuestion()->create(['customer_id' => $anonymous->id]);

    app(MergeAnonymousCustomer::class)($anonymous, $verified);

    expect($conversation->fresh()?->customer_id)->toBe($verified->id);
});

it('moves an active block on the anonymous customer to the verified customer', function (): void {
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();
    $block = CustomerBlock::factory()->create(['customer_id' => $anonymous->id]);

    app(MergeAnonymousCustomer::class)($anonymous, $verified);

    expect($block->fresh()?->customer_id)->toBe($verified->id);
});

it('re-points a message the anonymous customer sent to the verified customer', function (): void {
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();
    $conversation = Conversation::factory()->listingQuestion()->create(['customer_id' => $anonymous->id]);
    $message = Message::factory()->from($anonymous)->create(['conversation_id' => $conversation->id]);

    app(MergeAnonymousCustomer::class)($anonymous, $verified);

    expect($message->fresh()?->sender_id)->toBe($verified->id)
        ->and($message->fresh()?->sender_type)->toBe('customer');
});

it('does not read the verified customer\'s own merged message as unread to them', function (): void {
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();
    $conversation = Conversation::factory()->listingQuestion()->create(['customer_id' => $anonymous->id]);
    Message::factory()->from($anonymous)->create(['conversation_id' => $conversation->id]);

    app(MergeAnonymousCustomer::class)($anonymous, $verified);

    expect($conversation->messages()->unreadBy($verified)->count())->toBe(0);
});
