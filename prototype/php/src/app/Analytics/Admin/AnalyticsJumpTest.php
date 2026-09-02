<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\JumpKind;
use App\Http\Middleware\LogRequestStory;
use App\Models\Customer;
use Illuminate\Http\Request;

/** Binds a request carrying `$ip` so `Analytics::recordEvent()` stamps it
 * onto the next event it buffers — {@see \App\Analytics\RequestFacts::current()}
 * reads whatever request the container holds. */
function bindRequestFromIp(string $ip): void
{
    $request = Request::create('/', server: ['REMOTE_ADDR' => $ip]);
    $request->attributes->set(LogRequestStory::REQUEST_ID_ATTRIBUTE, 'req_01J00000000000000000000ABC');
    app()->instance('request', $request);
}

it('jumps to the listing a prefix of its id names uniquely', function (): void {
    $listing = $this->listing($this->seller(), ['title' => 'The Burrow at Dusk']);

    $jump = AnalyticsJump::for(substr($listing->id, 0, 10));
    assert($jump instanceof Jump);

    expect($jump->id)->toBe($listing->id)
        ->and($jump->caption)->toBe('listing · The Burrow at Dusk')
        ->and($jump->kind)->toBe(JumpKind::Listing);
});

it('jumps to the customer its id names uniquely', function (): void {
    $anonymous = $this->anonymousCustomer();

    $jump = AnalyticsJump::for($anonymous->id);
    assert($jump instanceof Jump);

    expect($jump->id)->toBe($anonymous->id)
        ->and($jump->caption)->toBe('anonymous customer · never signed in')
        ->and($jump->kind)->toBe(JumpKind::Actor);

    $verified = Customer::factory()->create(['email' => 'hermione@example.com']);
    $verifiedJump = AnalyticsJump::for($verified->id);
    assert($verifiedJump instanceof Jump);

    expect($verifiedJump->caption)->toBe('verified customer · hermione@example.com');
});

it('finds nothing for an id prefix shorter than six characters', function (): void {
    $listing = $this->listing($this->seller());

    expect(AnalyticsJump::for(substr($listing->id, 0, 5)))->toBeNull();
});

it('finds nothing for an id prefix naming no row', function (): void {
    expect(AnalyticsJump::for('lst_missingrow0000000000000000'))->toBeNull();
});

it('finds nothing for a blank query', function (): void {
    expect(AnalyticsJump::for(''))->toBeNull()
        ->and(AnalyticsJump::for('   '))->toBeNull();
});

it('jumps to the actor an ip names uniquely across every event it recorded', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->anonymousCustomer();
    $analytics = app(Analytics::class);

    bindRequestFromIp('185.220.101.42');
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $jump = AnalyticsJump::for('185.220.101.42');
    assert($jump instanceof Jump);

    expect($jump->id)->toBe($customer->id)
        ->and($jump->kind)->toBe(JumpKind::Actor);
});

it('finds nothing for an ip more than one actor used', function (): void {
    $listing = $this->listing($this->seller());
    $customerOne = $this->anonymousCustomer();
    $customerTwo = $this->anonymousCustomer();
    $analytics = app(Analytics::class);

    bindRequestFromIp('203.0.113.24');
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customerOne->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customerTwo->id, $this->moment('2026-08-19 10:00:00')));
    $analytics->flush();

    expect(AnalyticsJump::for('203.0.113.24'))->toBeNull();
});

it('finds nothing for an ip no event carries', function (): void {
    expect(AnalyticsJump::for('198.51.100.1'))->toBeNull();
});
