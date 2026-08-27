<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Configurator\ConfiguratorPublishValidation;
use App\Domain\Configurator\DescriptionSectionKind;
use App\Domain\RateLimiting\RateLimitValue;
use App\Models\DescriptionSection;
use Illuminate\Support\Facades\Config;

it('lists the listing’s description sections in position order', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    DescriptionSection::factory()->create(['listing_id' => $listing->id, 'position' => 0, 'title' => 'Care instructions']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/description-sections");

    $response->assertOk();
    $response->assertSee('Care instructions');
});

it('refuses another sellers description sections page', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));

    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/listings/{$listing->id}/description-sections");

    $response->assertNotFound();
});

it('adds a markdown section at the next position', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    DescriptionSection::factory()->create(['listing_id' => $listing->id, 'position' => 0]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/description-sections", [
        'kind' => 'care',
        'title' => 'Care',
        'body_md' => 'Hand wash only.',
    ]);

    $response->assertRedirect(route('seller.listings.description-sections.index', $listing));
    $section = DescriptionSection::where('listing_id', $listing->id)->where('position', 1)->sole();
    expect($section->kind)->toBe(DescriptionSectionKind::Care)
        ->and($section->body_md)->toBe('Hand wash only.');
});

it('adds a json-bodied section', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/description-sections", [
        'kind' => 'specs',
        'body_json' => '[{"label":"Height","value":"10 in"}]',
    ]);

    $section = DescriptionSection::where('listing_id', $listing->id)->sole();
    expect($section->kind)->toBe(DescriptionSectionKind::Specs)
        ->and($section->body_json)->toBe([['label' => 'Height', 'value' => '10 in']]);
});

it('refuses a sixteenth section', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    for ($i = 0; $i < ConfiguratorPublishValidation::MAX_SECTIONS; $i++) {
        DescriptionSection::factory()->create(['listing_id' => $listing->id, 'position' => $i]);
    }

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/description-sections", [
        'kind' => 'text',
    ]);

    $response->assertSessionHasErrors('kind');
    expect(DescriptionSection::where('listing_id', $listing->id)->count())->toBe(ConfiguratorPublishValidation::MAX_SECTIONS);
});

it('updates a section', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $section = DescriptionSection::factory()->create(['listing_id' => $listing->id, 'kind' => DescriptionSectionKind::Text]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/description-sections/{$section->id}", [
        'kind' => 'disclaimer',
        'body_md' => 'Colors may vary.',
    ]);

    $response->assertRedirect(route('seller.listings.description-sections.index', $listing));
    $updated = $section->fresh();
    expect($updated?->kind)->toBe(DescriptionSectionKind::Disclaimer)
        ->and($updated?->body_md)->toBe('Colors may vary.');
});

it('removes a section', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $section = DescriptionSection::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/description-sections/{$section->id}");

    $response->assertRedirect(route('seller.listings.description-sections.index', $listing));
    expect(DescriptionSection::find($section->id))->toBeNull();
});

it('refuses removing another sellers section', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));
    $section = DescriptionSection::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($this->seller(), 'seller')->delete("/seller/listings/{$listing->id}/description-sections/{$section->id}");

    $response->assertNotFound();
    expect(DescriptionSection::find($section->id))->not->toBeNull();
});

it('trips the listing-write limit adding a section', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/description-sections", ['kind' => 'text']);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/description-sections", ['kind' => 'text']);

    $response->assertStatus(429);
    expect(DescriptionSection::where('listing_id', $listing->id)->count())->toBe(1);
});

it('trips the listing-write limit updating a section', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $section = DescriptionSection::factory()->create(['listing_id' => $listing->id, 'kind' => DescriptionSectionKind::Text]);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/description-sections", ['kind' => 'text']);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/description-sections/{$section->id}", ['kind' => 'care']);

    $response->assertStatus(429);
    expect($section->fresh()?->kind)->toBe(DescriptionSectionKind::Text);
});

it('trips the listing-write limit removing a section', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $section = DescriptionSection::factory()->create(['listing_id' => $listing->id]);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/description-sections", ['kind' => 'text']);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/description-sections/{$section->id}");

    $response->assertStatus(429);
    expect(DescriptionSection::find($section->id))->not->toBeNull();
});
