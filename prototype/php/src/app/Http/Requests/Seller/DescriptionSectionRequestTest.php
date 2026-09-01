<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Configurator\ConfiguratorPublishValidation;
use App\Models\DescriptionSection;

it('refuses an unknown kind', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/description-sections", [
        'kind' => 'nonsense',
    ]);

    $response->assertSessionHasErrors(['kind']);
});

it('refuses a sixteenth section with its own message', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    for ($i = 0; $i < ConfiguratorPublishValidation::MAX_SECTIONS; $i++) {
        DescriptionSection::factory()->create(['listing_id' => $listing->id, 'position' => $i]);
    }

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/description-sections", [
        'kind' => 'text',
    ]);

    $response->assertSessionHasErrors([
        'kind' => 'This listing already holds '.ConfiguratorPublishValidation::MAX_SECTIONS.' description sections, the most allowed.',
    ]);
    expect(DescriptionSection::where('listing_id', $listing->id)->count())->toBe(ConfiguratorPublishValidation::MAX_SECTIONS);
});

it('drops blank size-chart rows and keeps the rest', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/description-sections", [
        'kind' => 'size_chart',
        'size_chart' => [
            ['label' => 'S', 'value1' => '34', 'value2' => '26'],
            ['label' => '', 'value1' => '', 'value2' => ''],
        ],
    ]);

    expect(DescriptionSection::where('listing_id', $listing->id)->sole()->body_json)->toBe([
        ['label' => 'S', 'value1' => '34', 'value2' => '26'],
    ]);
});

it('ignores a malformed size-chart row that is not an array', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/description-sections", [
        'kind' => 'size_chart',
        'size_chart' => ['not a row', ['label' => 'M', 'value1' => '38', 'value2' => '28']],
    ]);

    expect(DescriptionSection::where('listing_id', $listing->id)->sole()->body_json)->toBe([
        ['label' => 'M', 'value1' => '38', 'value2' => '28'],
    ]);
});

it('treats an all-blank set of size-chart rows as no body', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/description-sections", [
        'kind' => 'size_chart',
        'size_chart' => [
            ['label' => '', 'value1' => '', 'value2' => ''],
        ],
    ]);

    expect(DescriptionSection::where('listing_id', $listing->id)->sole()->body_json)->toBeNull();
});
