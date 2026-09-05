<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Domain\Configurator\ConfiguratorPublishRefused;
use App\Domain\DomainRuleViolation;
use App\Domain\Listings\ListingStatus;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use Tests\CapturedStory;

it('puts a draft up for sale and tells the publish story', function (): void {
    $listing = $this->listing($this->seller(), ['status' => ListingStatus::Draft]);
    $log = CapturedStory::capture();

    app(ChangeListingStatus::class)($listing, ListingStatus::ForSale);

    expect($listing->refresh()->status)->toBe(ListingStatus::ForSale)
        ->and($log->line('listing.transition', 'did')['data'])->toMatchArray(['status_to' => 'for_sale'])
        ->and($log->line('listing.publish', 'did')['data'])->toMatchArray(['slug' => $listing->slug]);
});

it('archives a listing that is for sale with no publish line', function (): void {
    $listing = $this->listing($this->seller(), ['status' => ListingStatus::ForSale]);
    $log = CapturedStory::capture();

    app(ChangeListingStatus::class)($listing, ListingStatus::Archived);

    expect($listing->refresh()->status)->toBe(ListingStatus::Archived)
        ->and($log->linesFor('listing.publish'))->toBe([]);
});

it('refuses to publish a listing with configurator issues and leaves it a draft', function (): void {
    $listing = $this->listing($this->seller(), ['status' => ListingStatus::Draft]);
    $axis = OptionAxis::factory()->standalone()->create(['listing_id' => $listing->id, 'name' => 'Size']);
    OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => '11x14', 'price_cents' => null]);
    $log = CapturedStory::capture();

    expect(fn () => app(ChangeListingStatus::class)($listing, ListingStatus::ForSale))
        ->toThrow(ConfiguratorPublishRefused::class);

    expect($listing->refresh()->status)->toBe(ListingStatus::Draft)
        ->and($log->line('listing.transition', 'refused')['data'])->toBeArray()->toHaveKey('issues');
});

it('refuses a transition the status does not allow', function (): void {
    $listing = $this->listing($this->seller(), ['status' => ListingStatus::Archived]);

    expect(fn () => app(ChangeListingStatus::class)($listing, ListingStatus::Sold))
        ->toThrow(DomainRuleViolation::class);

    expect($listing->refresh()->status)->toBe(ListingStatus::Archived);
});
