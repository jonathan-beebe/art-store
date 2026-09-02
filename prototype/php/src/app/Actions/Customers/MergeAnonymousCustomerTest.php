<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Domain\Messaging\ConversationSubject;
use App\Domain\Money\Money;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerBlock;
use App\Models\CustomerMerge;
use App\Models\Favorite;
use App\Models\Fulfillment;
use App\Models\ListingEvent;
use App\Models\Message;
use App\Models\Order;
use App\Models\Seller;
use App\Notifications\ItemSold;
use App\Notifications\OrderShipped;
use DateTimeImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\CapturedStory;

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
    // customer_blocks carries columns this test knows nothing about, so the
    // table-driven re-pointing is proven against a row this test can write on its own.
    Schema::dropIfExists('customer_blocks');
    Schema::create('customer_blocks', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('customer_id');
    });
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();
    $bystander = Customer::factory()->create();
    DB::table('customer_blocks')->insert([
        ['customer_id' => $anonymous->id],
        ['customer_id' => $bystander->id],
    ]);

    app(MergeAnonymousCustomer::class)($anonymous, $verified);

    expect(DB::table('customer_blocks')->where('customer_id', $verified->id)->count())->toBe(1)
        ->and(DB::table('customer_blocks')->where('customer_id', $anonymous->id)->count())->toBe(0)
        ->and(DB::table('customer_blocks')->where('customer_id', $bystander->id)->count())->toBe(1);
});

it('skips a customer-owned table that does not exist', function (): void {
    Schema::dropIfExists('refunds');
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();

    app(MergeAnonymousCustomer::class)($anonymous, $verified);

    expect(CustomerMerge::count())->toBe(1);
});

it('re-points listing events on the analytics connection through the model', function (): void {
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();
    $bystander = Customer::factory()->create();
    ListingEvent::factory()->create(['customer_id' => $anonymous->id]);
    ListingEvent::factory()->create(['customer_id' => $bystander->id]);

    app(MergeAnonymousCustomer::class)($anonymous, $verified);

    expect(ListingEvent::where('customer_id', $verified->id)->count())->toBe(1)
        ->and(ListingEvent::where('customer_id', $anonymous->id)->count())->toBe(0)
        ->and(ListingEvent::where('customer_id', $bystander->id)->count())->toBe(1);
});

it('still merges and logs a warning when the analytics store is unwritable', function (): void {
    $log = CapturedStory::capture();
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();
    ListingEvent::factory()->create(['customer_id' => $anonymous->id]);

    $originalDatabase = config('database.connections.analytics.database');
    // RefreshDatabase already opened a transaction on this PDO for the
    // current test (tests/TestCase.php's connectionsToTransact); purging
    // the connection below drops the wrapper without closing it, so it is
    // rolled back by hand once the test is done with it — otherwise the
    // next test to begin a transaction on the same cached in-memory PDO
    // finds one already open.
    $originalPdo = DB::connection('analytics')->getPdo();

    config()->set('database.connections.analytics.database', '/nonexistent/dir/analytics.sqlite3');
    DB::purge('analytics');

    try {
        app(MergeAnonymousCustomer::class)($anonymous, $verified);

        expect(CustomerMerge::where('anonymous_customer_id', $anonymous->id)->where('customer_id', $verified->id)->exists())->toBeTrue()
            ->and($log->line('app.log', 'doing')['level'])->toBe('warn');
    } finally {
        if ($originalPdo->inTransaction()) {
            $originalPdo->rollBack();
        }

        config()->set('database.connections.analytics.database', $originalDatabase);
        DB::purge('analytics');
    }
});

it('re-points the notifications addressed to the anonymous customer', function (): void {
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();
    $bystander = Customer::factory()->create();
    $anonymous->notify(new OrderShipped('ord_00000000000000000000000004', 'USPS', '94001'));
    $bystander->notify(new OrderShipped('ord_00000000000000000000000005', 'USPS', '94002'));

    app(MergeAnonymousCustomer::class)($anonymous, $verified);

    expect($verified->notifications()->count())->toBe(1)
        ->and($anonymous->notifications()->count())->toBe(0)
        ->and($bystander->notifications()->count())->toBe(1);
});

it('leaves a seller notification where it is when a customer merges', function (): void {
    $anonymous = Customer::factory()->anonymous()->create();
    $seller = Seller::factory()->create();
    $seller->notify(new ItemSold('ord_00000000000000000000000004', Money::fromCents(9000)));

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

it('keeps one fulfillment thread per subject after the merge', function (): void {
    $seller = Seller::factory()->create();
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();
    $order = Order::factory()->create(['customer_id' => $anonymous->id]);
    $fulfillment = Fulfillment::factory()->create(['order_id' => $order->id, 'seller_id' => $seller->id]);
    $now = new DateTimeImmutable('2026-08-20 09:00:00');
    Conversation::openFor(ConversationSubject::fulfillment($seller->id, $anonymous->id, $fulfillment->id), $now);

    app(MergeAnonymousCustomer::class)($anonymous, $verified);

    Conversation::openFor(ConversationSubject::fulfillment($seller->id, $verified->id, $fulfillment->id), $now);

    expect(Conversation::count())->toBe(1);
});

it('leaves the owner with exactly one cart after the merge', function (): void {
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();
    $this->cartFor($anonymous);
    $this->cartFor($verified);

    app(MergeAnonymousCustomer::class)($anonymous, $verified);

    expect(Cart::whereIn('customer_id', [$anonymous->id, $verified->id])->count())->toBe(1)
        ->and($verified->cart())->not->toBeNull();
});

it('gives the verified customer a cart when neither side had one', function (): void {
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();

    app(MergeAnonymousCustomer::class)($anonymous, $verified);

    expect(Cart::where('customer_id', $verified->id)->count())->toBe(1);
});

it('sums cart quantities across both carts and clamps the sum to the listing\'s stock, both visible on the merged cart', function (): void {
    $summed = $this->listing($this->seller(), ['quantity' => 10]);
    $clamped = $this->listing($this->seller(), ['quantity' => 4]);
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();
    $anonymousCart = $this->cartFor($anonymous);
    $verifiedCart = $this->cartFor($verified);
    CartItem::factory()->create(['cart_id' => $anonymousCart->id, 'listing_id' => $summed->id, 'quantity' => 2]);
    CartItem::factory()->create(['cart_id' => $verifiedCart->id, 'listing_id' => $summed->id, 'quantity' => 1]);
    CartItem::factory()->create(['cart_id' => $anonymousCart->id, 'listing_id' => $clamped->id, 'quantity' => 3]);
    CartItem::factory()->create(['cart_id' => $verifiedCart->id, 'listing_id' => $clamped->id, 'quantity' => 3]);

    app(MergeAnonymousCustomer::class)($anonymous, $verified);

    $items = $verified->cart()->items()->get();
    expect($items->firstWhere('listing_id', $summed->id)?->quantity)->toBe(3)
        ->and($items->firstWhere('listing_id', $clamped->id)?->quantity)->toBe(4);
});

it('moves an anonymous favorite the verified customer lacks and drops the one they both had, visible on the verified customer', function (): void {
    $moved = $this->listing($this->seller());
    $duplicated = $this->listing($this->seller());
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();
    Favorite::factory()->create(['customer_id' => $anonymous->id, 'listing_id' => $moved->id]);
    Favorite::factory()->create(['customer_id' => $anonymous->id, 'listing_id' => $duplicated->id]);
    Favorite::factory()->create(['customer_id' => $verified->id, 'listing_id' => $duplicated->id]);

    app(MergeAnonymousCustomer::class)($anonymous, $verified);

    expect(Favorite::where('customer_id', $verified->id)->where('listing_id', $moved->id)->exists())->toBeTrue()
        ->and(Favorite::where('listing_id', $duplicated->id)->count())->toBe(1)
        ->and(Favorite::where('customer_id', $anonymous->id)->exists())->toBeFalse();
});
