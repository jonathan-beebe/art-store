<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Configurator\ConfiguratorPublishValidation;
use App\Domain\Configurator\DescriptionSectionKind;
use App\Domain\RateLimiting\RateLimitValue;
use App\Models\DescriptionSection;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use Illuminate\Support\Facades\Config;
use LogicException;

it('lists the listing’s sections in position order', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    DescriptionSection::factory()->create(['listing_id' => $listing->id, 'position' => 0, 'title' => 'Care instructions']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/description-sections");

    $response->assertOk();
    $response->assertSee('Listing page sections');
    $response->assertSee('Care instructions');
});

it('gives the size-chart table’s header cells a column scope for assistive tech', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/description-sections?kind=size_chart");

    $response->assertOk();
    $response->assertSee('<th scope="col"', false);
});

it('IMPRV-015: the buyer panel preserves this screens own query params across a live refresh', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Size']);
    OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => '8 oz', 'is_default' => true]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/description-sections?kind=nonsense");

    $response->assertSee('<input type="hidden" name="kind" value="nonsense">', escape: false);
});

it('D1: renders sections as a clean titled page in the buyer panel', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Screen-Printed Wildflower Tee']);
    DescriptionSection::factory()->create(['listing_id' => $listing->id, 'position' => 0, 'kind' => DescriptionSectionKind::Text, 'title' => 'How to order', 'body_md' => 'Orders print Mondays.']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/description-sections");

    $response->assertSee('What buyers see');
    $response->assertSeeInOrder(['Screen-Printed Wildflower Tee', 'How to order', 'Orders print Mondays.']);
});

it('D3: renders a size chart as an actual table in the buyer panel', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    DescriptionSection::factory()->json(DescriptionSectionKind::SizeChart, [
        ['label' => 'S', 'value1' => '36 in', 'value2' => '27 in'],
        ['label' => 'M', 'value1' => '40 in', 'value2' => '28 in'],
    ])->create(['listing_id' => $listing->id, 'position' => 0, 'title' => 'Size chart']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/description-sections");

    $response->assertSee('<table', false);
    $response->assertSeeInOrder(['S', '36 in', '27 in', 'M', '40 in', '28 in']);
});

it('D2: how-to-order leads the page today, with pinning it beside the choices named as coming', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    DescriptionSection::factory()->create(['listing_id' => $listing->id, 'position' => 0, 'kind' => DescriptionSectionKind::Text, 'title' => 'How to order', 'body_md' => 'Orders print Mondays.']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/description-sections");

    $response->assertSee("Leads the page today. Pinning it beside the buyer's choices is", escape: false);
    $response->assertSee('coming — not in this version', false);
});

it('D4: shows the honest note about per-listing sections', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/description-sections");

    $response->assertSee(
        'Reusing one section across all your listings — the same disclaimer on 40 pages, edited once — is',
        escape: false,
    );
    $response->assertSee('coming — not in this version', false);
    $response->assertSee("Until then it's per listing.", escape: false);
});

it('shows authored kind names, never the schema word', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    DescriptionSection::factory()->create(['listing_id' => $listing->id, 'position' => 0, 'kind' => DescriptionSectionKind::Faq, 'body_md' => null]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/description-sections");

    $response->assertSee('Q &amp; A', false);
    $response->assertSee('Details list');
    $response->assertSee('Size chart');
    $response->assertSee('The fine print');
    $response->assertDontSee('Faq');
    $response->assertDontSee('Body (JSON)');
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
    $response->assertSessionHas('status', 'Section added.');
    $section = DescriptionSection::where('listing_id', $listing->id)->where('position', 1)->sole();
    expect($section->kind)->toBe(DescriptionSectionKind::Care)
        ->and($section->body_md)->toBe('Hand wash only.');
});

it('adds a details-list section from label/value rows', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/description-sections", [
        'kind' => 'specs',
        'spec' => [
            ['label' => 'Height', 'value' => '10 in'],
            ['label' => '', 'value' => ''],
        ],
    ]);

    $section = DescriptionSection::where('listing_id', $listing->id)->sole();
    expect($section->kind)->toBe(DescriptionSectionKind::Specs)
        ->and($section->body_json)->toBe([['label' => 'Height', 'value' => '10 in']]);
});

it('adds a size-chart section from labeled table rows', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/description-sections", [
        'kind' => 'size_chart',
        'size_chart' => [
            ['label' => 'S', 'value1' => '36 in', 'value2' => '27 in'],
            ['label' => '', 'value1' => '', 'value2' => ''],
        ],
    ]);

    $section = DescriptionSection::where('listing_id', $listing->id)->sole();
    expect($section->kind)->toBe(DescriptionSectionKind::SizeChart)
        ->and($section->body_json)->toBe([['label' => 'S', 'value1' => '36 in', 'value2' => '27 in']]);
});

it('adds a Q & A section from question/answer rows', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/description-sections", [
        'kind' => 'faq',
        'faq' => [
            ['question' => 'Does it run small?', 'answer' => 'True to size.'],
        ],
    ]);

    $section = DescriptionSection::where('listing_id', $listing->id)->sole();
    expect($section->kind)->toBe(DescriptionSectionKind::Faq)
        ->and($section->body_json)->toBe([['question' => 'Does it run small?', 'answer' => 'True to size.']]);
});

it('drops a row missing any one of its columns', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/description-sections", [
        'kind' => 'specs',
        'spec' => [
            ['label' => 'Height', 'value' => ''],
            ['label' => '', 'value' => '10 in'],
        ],
    ]);

    expect(DescriptionSection::where('listing_id', $listing->id)->sole()->body_json)->toBeNull();
});

it('ignores a malformed row that is not an array of columns', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/description-sections", [
        'kind' => 'specs',
        'spec' => ['not a row', ['label' => 'Height', 'value' => '10 in']],
    ]);

    expect(DescriptionSection::where('listing_id', $listing->id)->sole()->body_json)->toBe([['label' => 'Height', 'value' => '10 in']]);
});

it('refuses an unknown kind', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/description-sections", [
        'kind' => 'nonsense',
    ]);

    $response->assertSessionHasErrors('kind');
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
    $response->assertSessionHas('status', 'Section updated.');
    $updated = $section->fresh();
    expect($updated?->kind)->toBe(DescriptionSectionKind::Disclaimer)
        ->and($updated?->body_md)->toBe('Colors may vary.');
});

it('answers not found updating a section from another listing', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $otherListing = $this->listing($seller);
    $section = DescriptionSection::factory()->create(['listing_id' => $otherListing->id]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/description-sections/{$section->id}", [
        'kind' => 'disclaimer',
        'body_md' => 'Colors may vary.',
    ]);

    $response->assertNotFound();
});

it('removes a section', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $section = DescriptionSection::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/description-sections/{$section->id}");

    $response->assertRedirect(route('seller.listings.description-sections.index', $listing));
    $response->assertSessionHas('status', 'Section removed.');
    expect(DescriptionSection::find($section->id))->toBeNull();
});

it('trips the listing-write limit', function (string $action): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $section = DescriptionSection::factory()->create(['listing_id' => $listing->id, 'kind' => DescriptionSectionKind::Text]);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/description-sections", ['kind' => 'text']);

    $response = match ($action) {
        'adding' => $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/description-sections", ['kind' => 'text']),
        'updating' => $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/description-sections/{$section->id}", ['kind' => 'care']),
        'removing' => $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/description-sections/{$section->id}"),
        default => throw new LogicException("Unknown action: {$action}"),
    };

    $response->assertStatus(429);
    match ($action) {
        'adding' => expect(DescriptionSection::where('listing_id', $listing->id)->count())->toBe(2),
        'updating' => expect($section->fresh()?->kind)->toBe(DescriptionSectionKind::Text),
        'removing' => expect(DescriptionSection::find($section->id))->not->toBeNull(),
    };
})->with(['adding', 'updating', 'removing']);
