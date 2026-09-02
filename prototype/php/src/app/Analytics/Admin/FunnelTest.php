<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\FinalizeOrder;
use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\AnalyticsRange;
use App\Http\Middleware\LogRequestStory;
use App\Models\Order;
use App\Support\RequestMarks;
use Illuminate\Http\Request;

/**
 * Binds an in-flight request carrying the given session cookie, so the next
 * recorded event reads it back from {@see \App\Analytics\RequestFacts::current()}.
 */
function bindSession(string $sessionId): void
{
    $request = Request::create('/', cookies: [RequestMarks::SESSION_COOKIE => $sessionId]);
    $request->attributes->set(LogRequestStory::REQUEST_ID_ATTRIBUTE, 'req_01J00000000000000000000ABC');
    app()->instance('request', $request);
}

it('orders every step from visitors to orders paid, zero-filled with nothing recorded', function (): void {
    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));

    $funnel = Funnel::forRange($range);
    $labels = array_column($funnel->steps, 'label');

    expect($labels)->toBe([
        'Visitors', 'Listing views', 'Favorites', 'Cart adds',
        'Checkouts opened', 'Orders placed', 'Orders paid',
    ]);

    foreach ($funnel->steps as $step) {
        expect($step->current)->toBe(0)
            ->and($step->previous)->toBe(0)
            ->and($step->rate)->toBeNull();
    }
});

it('counts visitors as distinct session ids in the range, nulls excluded', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    bindSession('sess-1');
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00'), 'a'));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-20 09:00:00'), 'b'));
    bindSession('sess-2');
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-21 09:00:00'), 'c'));
    // No request id attribute stamped, so RequestFacts::current() reads
    // this one as no request at all — the same as a seeder or console run.
    app()->instance('request', Request::create('/'));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-22 09:00:00'), 'd'));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $funnel = Funnel::forRange($range);

    expect($funnel->steps[0]->current)->toBe(2);
});

it('carries the rate from each step\'s own prerequisite, not always the step drawn before it', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    foreach (range(1, 10) as $i) {
        bindSession("sess-{$i}");
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00'), "v{$i}"));
    }
    foreach (range(1, 4) as $i) {
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingFavorite, $listing->id, $customer->id, $this->moment('2026-08-19 10:00:00')));
    }
    foreach (range(1, 6) as $i) {
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingCartAdd, $listing->id, $customer->id, $this->moment('2026-08-19 11:00:00')));
    }
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $funnel = Funnel::forRange($range);

    [$visitors, $views, $favorites, $cartAdds] = $funnel->steps;

    expect($visitors->current)->toBe(10)
        ->and($views->current)->toBe(10)
        ->and($views->rate?->text)->toBe('100%')
        ->and($views->rate?->ofLabel)->toBe('visitors')
        ->and($favorites->current)->toBe(4)
        ->and($favorites->rate?->text)->toBe('40%')
        ->and($favorites->rate?->ratio)->toBe(0.4)
        ->and($favorites->rate?->ofLabel)->toBe('views')
        // Cart adds' prerequisite is listing views, not favorites — its
        // rate reads 60% of the 10 views, not 150% of the 4 favorites.
        ->and($cartAdds->current)->toBe(6)
        ->and($cartAdds->rate?->text)->toBe('60%')
        ->and($cartAdds->rate?->ofLabel)->toBe('views');
});

it('notes the cancelled count on the paid step rather than a step of its own', function (): void {
    $seller = $this->seller();
    $paid = $this->orderFor($this->verifiedCustomer(), $this->listing($seller));
    $cancelled = $this->orderFor($this->verifiedCustomer(), $this->listing($seller));

    app(FinalizeOrder::class)($paid, '4242 4242 4242 4242', $this->moment('2026-08-20 10:00:00'));
    app(CancelOrder::class)($cancelled, $this->moment('2026-08-20 11:00:00'));
    app(Analytics::class)->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $funnel = Funnel::forRange($range);
    $paidStep = $funnel->steps[6];

    expect($paidStep->label)->toBe('Orders paid')
        ->and($paidStep->current)->toBe(1)
        ->and($paidStep->note)->toBe('1 cancelled');
});

it('matches the app database\'s own placed and paid counts for the range', function (): void {
    $seller = $this->seller();
    $paidOne = $this->orderFor($this->verifiedCustomer(), $this->listing($seller));
    $paidTwo = $this->orderFor($this->verifiedCustomer(), $this->listing($seller));
    $this->orderFor($this->verifiedCustomer(), $this->listing($seller)); // placed, never finalized

    app(FinalizeOrder::class)($paidOne, '4242 4242 4242 4242', $this->moment('2026-08-20 10:00:00'));
    app(FinalizeOrder::class)($paidTwo, '4242 4242 4242 4242', $this->moment('2026-08-20 10:05:00'));
    app(Analytics::class)->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $funnel = Funnel::forRange($range);
    [, , , , , $placedStep, $paidStep] = $funnel->steps;

    $placedInRange = Order::query()->whereBetween('placed_at', [SqlInstant::format($range->start), SqlInstant::format($range->end)])->count();
    $paidInRange = Order::query()->whereNotNull('finalized_at')->whereBetween('finalized_at', [SqlInstant::format($range->start), SqlInstant::format($range->end)])->count();

    expect($placedStep->current)->toBe($placedInRange)->toBe(3)
        ->and($paidStep->current)->toBe($paidInRange)->toBe(2);
});

it('scopes a listing\'s funnel to its own view, favorite, and cart-add events', function (): void {
    $seller = $this->seller();
    $listingOne = $this->listing($seller);
    $listingTwo = $this->listing($seller);
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listingOne->id, $customer->id, $this->moment('2026-08-19 09:00:00'), 'a'));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listingTwo->id, $customer->id, $this->moment('2026-08-19 09:00:00'), 'b'));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $funnel = Funnel::forListing($listingOne->id, $range);

    expect($funnel->steps[1]->current)->toBe(1);
});

it('counts an order once for each listing it spans on that listing\'s own funnel', function (): void {
    $seller = $this->seller();
    $listingOne = $this->listing($seller);
    $listingTwo = $this->listing($seller);
    $order = $this->orderFor($this->verifiedCustomer(), $listingOne, $listingTwo);
    app(Analytics::class)->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));

    expect(Funnel::forListing($listingOne->id, $range)->steps[5]->current)->toBe(1)
        ->and(Funnel::forListing($listingTwo->id, $range)->steps[5]->current)->toBe(1)
        // The unscoped, store-wide funnel counts the same order once — it
        // reads the one order.place row, not one row per listing it spans.
        ->and(Funnel::forRange($range)->steps[5]->current)->toBe(1);

    // One order, read from the app database, spans both.
    expect($order->items()->pluck('listing_id')->unique()->count())->toBe(2);
});

it('scopes a seller\'s funnel to their own listings\' orders', function (): void {
    $sellerOne = $this->seller('Weasley Studio');
    $sellerTwo = $this->seller('Rye Press');
    $this->orderFor($this->verifiedCustomer(), $this->listing($sellerOne));
    $this->orderFor($this->verifiedCustomer(), $this->listing($sellerTwo));
    app(Analytics::class)->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));

    expect(Funnel::forSeller($sellerOne, $range)->steps[5]->current)->toBe(1)
        ->and(Funnel::forSeller($sellerTwo, $range)->steps[5]->current)->toBe(1);
});

it('reads a seller with no listings as an empty funnel rather than every order', function (): void {
    $seller = $this->seller();
    $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));
    app(Analytics::class)->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));

    expect(Funnel::forSeller($seller, $range)->steps[5]->current)->toBe(0);
});
