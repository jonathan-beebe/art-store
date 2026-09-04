<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Models\Conversation;
use App\Models\Customer;
use Symfony\Component\DomCrawler\Crawler;

/**
 * `aria-current` names two different things across the seller and admin
 * panes: a tab or filter that narrows what a pane shows carries `true`;
 * a pane row (or a navigation control, like the listings view switch,
 * IMPRV-032) that names the page a link opens carries `page`. This sweeps
 * a page from each family and asserts every `[aria-current]` on it lands
 * on the right side of that pairing.
 */
function assertPaneRowsCarryPage(string $html, string $selector = '[data-pane-cell][aria-current]'): void
{
    $crawler = new Crawler($html);
    $rows = $crawler->filter($selector);

    expect($rows->count())->toBeGreaterThan(0);
    $rows->each(fn (Crawler $row) => expect($row->attr('aria-current'))->toBe('page'));
}

function assertTabsCarryTrue(string $html, string $selector): void
{
    $crawler = new Crawler($html);
    $tabs = $crawler->filter($selector.'[aria-current]');

    expect($tabs->count())->toBeGreaterThan(0);
    $tabs->each(fn (Crawler $tab) => expect($tab->attr('aria-current'))->toBe('true'));
}

it('IMPRV-030 pairs aria-current=page with a selected pane row and =true with a tab, on the orders pane', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);

    $html = (string) $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}")->getContent();

    assertPaneRowsCarryPage($html);
    assertTabsCarryTrue($html, 'nav[aria-label="Lane"] a');
});

it('IMPRV-030 pairs aria-current=page with a selected pane row and =true with a tab, on the messages pane', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'listing_id' => $this->listing($seller)->id,
    ]);

    $html = (string) $this->actingAs($seller, 'seller')->get(route('seller.messages.show', ['conversation' => $conversation, 'domain' => 'all']))->getContent();

    // The messaging inbox's rows carry no data-pane-cell of their own, so
    // this scopes to the conversation list itself, distinct from the
    // domain tabs above it.
    assertPaneRowsCarryPage($html, 'ul.divide-y a[aria-current]');
    assertTabsCarryTrue($html, 'nav[aria-label="Domain"] a');
});

it('IMPRV-030 pairs aria-current=page with a selected pane row, on the listings pane', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $html = (string) $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}")->getContent();

    assertPaneRowsCarryPage($html);
});

it('IMPRV-032 pairs aria-current=page with the listings view switch, a navigation control', function (): void {
    $seller = $this->seller();

    $html = (string) $this->actingAs($seller, 'seller')->get('/seller/listings?view=table')->getContent();

    assertPaneRowsCarryPage($html, '[role="group"][aria-label="View"] a[aria-current]');
});

it('IMPRV-030 pairs aria-current=page with a selected pane row on an admin pane', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller());

    $html = (string) $this->actingAs($this->admin(), 'admin')->get(route('admin.orders.show', $fulfillment->order))->getContent();

    assertPaneRowsCarryPage($html);
});
