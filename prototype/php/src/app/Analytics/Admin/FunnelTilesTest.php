<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\AnalyticsRange;
use App\Http\Middleware\LogRequestStory;
use App\Models\Funnel;
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

it('reads no funnels as no tiles', function (): void {
    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));

    expect(FunnelTiles::forRange($range))->toBe([]);
});

it('reads one tile per funnel, in position order', function (): void {
    Funnel::factory()->create(['name' => 'Second', 'position' => 2, 'steps' => ['checkout.open', 'order.pay']]);
    Funnel::factory()->create(['name' => 'First', 'position' => 1, 'steps' => ['listing.view', 'listing.cart_add']]);

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $tiles = FunnelTiles::forRange($range);

    expect(array_column($tiles, 'name'))->toBe(['First', 'Second']);
});

it('reads end-to-end conversion as the last step\'s sessions over visitors', function () use ($bindSession): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    foreach (range(1, 10) as $i) {
        $bindSession("sess-{$i}");
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00'), "v{$i}"));

        if ($i <= 4) {
            $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingCartAdd, $listing->id, $customer->id, $this->moment('2026-08-19 10:00:00'), "c{$i}"));
        }
    }
    $analytics->flush();

    Funnel::factory()->create(['name' => 'Gift Shopping', 'steps' => ['listing.view', 'listing.cart_add']]);

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $tile = FunnelTiles::forRange($range)[0];

    expect($tile->conversionText)->toBe('40%')
        ->and($tile->change->text)->not->toBeEmpty();
});

it('reads a range with no visitors as "—" rather than a division', function (): void {
    Funnel::factory()->create(['steps' => ['listing.view', 'listing.cart_add']]);

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $tile = FunnelTiles::forRange($range)[0];

    expect($tile->conversionText)->toBe('—');
});

it('caps tiles at eight, however many funnels exist', function (): void {
    foreach (range(1, 10) as $position) {
        Funnel::factory()->create(['position' => $position]);
    }

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));

    expect(FunnelTiles::forRange($range))->toHaveCount(8);
});
