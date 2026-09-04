<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\FinalizeOrder;
use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Analytics\FunnelDefinition;
use App\Domain\Seeding\Lcg;
use App\Http\Middleware\LogRequestStory;
use App\Models\Order;
use App\Support\RequestMarks;
use Illuminate\Http\Request;

/**
 * Binds an in-flight request carrying the given session cookie, so the next
 * recorded event reads it back from {@see \App\Analytics\RequestFacts::current()}.
 */
$bindSession = function (string $sessionId): void {
    $request = Request::create('/', cookies: [RequestMarks::SESSION_COOKIE => $sessionId]);
    $request->attributes->set(LogRequestStory::REQUEST_ID_ATTRIBUTE, 'req_01J00000000000000000000ABC');
    app()->instance('request', $request);
};

it('orders visitors then one step per definition name, zero-filled with nothing recorded', function (): void {
    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));

    $funnel = Funnel::forRange(FunnelDefinition::storefront(), $range);

    expect(array_column($funnel->steps, 'key'))->toBe([
        'visitors', 'listing.view', 'listing.cart_add', 'checkout.open', 'order.place', 'order.pay',
    ]);
    expect(array_column($funnel->steps, 'label'))->toBe([
        'Visitors', 'Listing views', 'Cart adds', 'Checkouts opened', 'Orders placed', 'Orders paid',
    ]);

    foreach ($funnel->steps as $step) {
        expect($step->current)->toBe(0)
            ->and($step->previous)->toBe(0)
            ->and($step->rate)->toBeNull()
            ->and($step->shareOfFirst)->toBe(0)
            ->and($step->previousShareOfFirst)->toBe(0)
            ->and($step->isLargestDrop)->toBeFalse();
    }

    [, $views, , , , $paid] = $funnel->steps;
    expect($views->side)->toBe('0 favorited')
        ->and($paid->note)->toBe('0 cancelled');
});

it('counts visitors as distinct session ids in the range, nulls excluded', function () use ($bindSession): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $bindSession('sess-1');
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00'), 'a'));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-20 09:00:00'), 'b'));
    $bindSession('sess-2');
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-21 09:00:00'), 'c'));
    // No request id attribute stamped, so RequestFacts::current() reads
    // this one as no request at all — the same as a seeder or console run.
    app()->instance('request', Request::create('/'));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-22 09:00:00'), 'd'));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $funnel = Funnel::forRange(FunnelDefinition::storefront(), $range);

    expect($funnel->steps[0]->current)->toBe(2);
});

it('counts a named step as distinct sessions, not events — a session viewing three times counts once', function () use ($bindSession): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $bindSession('sess-1');
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00'), 'a'));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:05:00'), 'b'));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:10:00'), 'c'));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $funnel = Funnel::forRange(FunnelDefinition::storefront(), $range);

    expect($funnel->steps[1]->current)->toBe(1);
});

it('carries the rate and share against the step drawn immediately before it', function () use ($bindSession): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    foreach (range(1, 10) as $i) {
        $bindSession("sess-{$i}");
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00'), "v{$i}"));

        if ($i <= 6) {
            $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingCartAdd, $listing->id, $customer->id, $this->moment('2026-08-19 10:00:00'), "c{$i}"));
        }

        if ($i <= 3) {
            $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::CheckoutOpen, $listing->id, $customer->id, $this->moment('2026-08-19 11:00:00'), "o{$i}"));
        }
    }
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $definition = FunnelDefinition::of(['listing.view', 'listing.cart_add', 'checkout.open']);
    $funnel = Funnel::forRange($definition, $range);

    [$visitors, $views, $cartAdds, $checkoutsOpened] = $funnel->steps;

    expect($visitors->current)->toBe(10)
        ->and($views->current)->toBe(10)
        ->and($views->rate?->text)->toBe('100%')
        ->and($views->rate?->ofLabel)->toBe('visitors')
        ->and($views->shareOfFirst)->toBe(100)
        ->and($cartAdds->current)->toBe(6)
        ->and($cartAdds->rate?->text)->toBe('60%')
        ->and($cartAdds->rate?->ofLabel)->toBe('listing views')
        ->and($cartAdds->shareOfFirst)->toBe(60)
        ->and($checkoutsOpened->current)->toBe(3)
        ->and($checkoutsOpened->rate?->text)->toBe('50%')
        ->and($checkoutsOpened->rate?->ofLabel)->toBe('cart adds')
        ->and($checkoutsOpened->shareOfFirst)->toBe(30);
});

it('marks the one step whose rate is the lowest as the largest drop', function () use ($bindSession): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    foreach (range(1, 10) as $i) {
        $bindSession("sess-{$i}");
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00'), "v{$i}"));

        // Cart adds convert well (90%); checkout opens convert poorly (11%) —
        // the steepest drop in the chain.
        if ($i <= 9) {
            $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingCartAdd, $listing->id, $customer->id, $this->moment('2026-08-19 10:00:00'), "c{$i}"));
        }

        if ($i === 1) {
            $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::CheckoutOpen, $listing->id, $customer->id, $this->moment('2026-08-19 11:00:00'), 'o1'));
        }
    }
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $definition = FunnelDefinition::of(['listing.view', 'listing.cart_add', 'checkout.open']);
    $funnel = Funnel::forRange($definition, $range);

    [, $views, $cartAdds, $checkoutsOpened] = $funnel->steps;

    expect($views->isLargestDrop)->toBeFalse()
        ->and($cartAdds->isLargestDrop)->toBeFalse()
        ->and($checkoutsOpened->isLargestDrop)->toBeTrue();
});

it('carries favorited sessions as a side count on the listing-view step', function () use ($bindSession): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $bindSession('sess-1');
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingFavorite, $listing->id, $customer->id, $this->moment('2026-08-19 09:05:00')));
    $bindSession('sess-2');
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:10:00')));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $funnel = Funnel::forRange(FunnelDefinition::storefront(), $range);

    expect($funnel->steps[1]->side)->toBe('1 favorited');
});

it('notes the cancelled sessions on the paid step rather than a step of its own', function () use ($bindSession): void {
    $seller = $this->seller();
    $paid = $this->orderFor($this->verifiedCustomer(), $this->listing($seller));
    $cancelled = $this->orderFor($this->verifiedCustomer(), $this->listing($seller));

    $bindSession('sess-pay');
    app(FinalizeOrder::class)($paid, '4242 4242 4242 4242', $this->moment('2026-08-20 10:00:00'));
    $bindSession('sess-cancel');
    app(CancelOrder::class)($cancelled, $this->moment('2026-08-20 11:00:00'));
    app(Analytics::class)->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $funnel = Funnel::forRange(FunnelDefinition::storefront(), $range);
    $paidStep = $funnel->steps[5];

    expect($paidStep->label)->toBe('Orders paid')
        ->and($paidStep->current)->toBe(1)
        ->and($paidStep->note)->toBe('1 cancelled');
});

it('matches the app database\'s own placed and paid counts for the range', function () use ($bindSession): void {
    $seller = $this->seller();

    $bindSession('sess-place-1');
    $paidOne = $this->orderFor($this->verifiedCustomer(), $this->listing($seller));
    $bindSession('sess-place-2');
    $paidTwo = $this->orderFor($this->verifiedCustomer(), $this->listing($seller));
    $bindSession('sess-place-3');
    $this->orderFor($this->verifiedCustomer(), $this->listing($seller)); // placed, never finalized

    $bindSession('sess-pay-1');
    app(FinalizeOrder::class)($paidOne, '4242 4242 4242 4242', $this->moment('2026-08-20 10:00:00'));
    $bindSession('sess-pay-2');
    app(FinalizeOrder::class)($paidTwo, '4242 4242 4242 4242', $this->moment('2026-08-20 10:05:00'));
    app(Analytics::class)->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $funnel = Funnel::forRange(FunnelDefinition::storefront(), $range);
    [, , , , $placedStep, $paidStep] = $funnel->steps;

    $placedInRange = Order::query()->whereBetween('placed_at', [SqlInstant::format($range->start), SqlInstant::format($range->end)])->count();
    $paidInRange = Order::query()->whereNotNull('finalized_at')->whereBetween('finalized_at', [SqlInstant::format($range->start), SqlInstant::format($range->end)])->count();

    expect($placedStep->current)->toBe($placedInRange)->toBe(3)
        ->and($paidStep->current)->toBe($paidInRange)->toBe(2);
});

it('scopes a listing\'s funnel to its own view and cart-add events', function () use ($bindSession): void {
    $seller = $this->seller();
    $listingOne = $this->listing($seller);
    $listingTwo = $this->listing($seller);
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $bindSession('sess-1');
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listingOne->id, $customer->id, $this->moment('2026-08-19 09:00:00'), 'a'));
    $bindSession('sess-2');
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listingTwo->id, $customer->id, $this->moment('2026-08-19 09:00:00'), 'b'));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $funnel = Funnel::forListing(FunnelDefinition::storefront(), $listingOne->id, $range);

    expect($funnel->steps[1]->current)->toBe(1);
});

it('counts an order once for each listing it spans on that listing\'s own funnel', function () use ($bindSession): void {
    $seller = $this->seller();
    $listingOne = $this->listing($seller);
    $listingTwo = $this->listing($seller);
    $bindSession('sess-1');
    $order = $this->orderFor($this->verifiedCustomer(), $listingOne, $listingTwo);
    app(Analytics::class)->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $definition = FunnelDefinition::storefront();

    expect(Funnel::forListing($definition, $listingOne->id, $range)->steps[4]->current)->toBe(1)
        ->and(Funnel::forListing($definition, $listingTwo->id, $range)->steps[4]->current)->toBe(1)
        // The unscoped, store-wide funnel counts the same session once — it
        // reads the one order.place row the order wrote.
        ->and(Funnel::forRange($definition, $range)->steps[4]->current)->toBe(1);

    // One order, read from the app database, spans both.
    expect($order->items()->pluck('listing_id')->unique()->count())->toBe(2);
});

it('scopes a seller\'s funnel to their own listings\' orders', function () use ($bindSession): void {
    $sellerOne = $this->seller('Weasley Studio');
    $sellerTwo = $this->seller('Rye Press');
    $bindSession('sess-1');
    $this->orderFor($this->verifiedCustomer(), $this->listing($sellerOne));
    $bindSession('sess-2');
    $this->orderFor($this->verifiedCustomer(), $this->listing($sellerTwo));
    app(Analytics::class)->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $definition = FunnelDefinition::storefront();

    expect(Funnel::forSeller($definition, $sellerOne, $range)->steps[4]->current)->toBe(1)
        ->and(Funnel::forSeller($definition, $sellerTwo, $range)->steps[4]->current)->toBe(1);
});

it('reads a seller with no listings as an empty funnel rather than every order', function () use ($bindSession): void {
    $seller = $this->seller();
    $bindSession('sess-1');
    $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));
    app(Analytics::class)->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));

    expect(Funnel::forSeller(FunnelDefinition::storefront(), $seller, $range)->steps[4]->current)->toBe(0);
});

it('never lets a step\'s count exceed the step before it, for random funnel-consistent event mixes', function () use ($bindSession): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);
    $names = FunnelDefinition::storefront()->steps;

    foreach ([11, 47, 991, 20260903] as $seed) {
        $rng = Lcg::seeded($seed);

        for ($session = 0; $session < 60; $session++) {
            $bindSession("prop-{$seed}-{$session}");

            // A funnel-consistent session only reaches a step after
            // clearing every step before it, the way the real store's
            // own action flow gates checkout on a cart and a sale on a
            // placed order — the loop stops at the first step this
            // session fails to clear.
            foreach ($names as $stepIndex => $name) {
                if ($rng->nextInt(100) >= 65) {
                    break;
                }

                $analytics->recordEvent(AnalyticsEvent::forListing($name, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00'), "prop-{$seed}-{$session}-{$stepIndex}"));
            }
        }
    }

    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $steps = Funnel::forRange(FunnelDefinition::storefront(), $range)->steps;

    for ($stepIndex = 1; $stepIndex < count($steps); $stepIndex++) {
        expect($steps[$stepIndex]->current)->toBeLessThanOrEqual($steps[$stepIndex - 1]->current);
    }

    // The property only says something once counts vary — a run that drew
    // zero attrition everywhere would pass vacuously.
    expect($steps[count($steps) - 1]->current)->toBeLessThan($steps[0]->current);
});
