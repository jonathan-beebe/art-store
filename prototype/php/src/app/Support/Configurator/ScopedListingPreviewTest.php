<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Models\Modifier;
use App\Models\OptionAxis;
use App\Models\OptionValue;

/**
 * @return \Illuminate\Database\Eloquent\Collection<int, Modifier>
 */
function scopedListingPreviewFixtureModifiers(\App\Models\Listing $listing): \Illuminate\Database\Eloquent\Collection
{
    return $listing->modifiers()->with('scopes.optionValue.axis.optionValues')->orderBy('position')->get();
}

it('is null with no scoped question', function (): void {
    $listing = test()->listing(test()->seller());
    Modifier::factory()->create(['listing_id' => $listing->id]);

    expect(ScopedListingPreview::resolve(scopedListingPreviewFixtureModifiers($listing)))->toBeNull();
});

it('builds the applies and does-not-apply selections from a sibling on the same choice', function (): void {
    $listing = test()->listing(test()->seller());
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Version']);
    $lettered = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Hand-lettered']);
    $blank = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Blank']);
    $modifier = Modifier::factory()->create(['listing_id' => $listing->id]);
    $modifier->scopes()->create(['option_value_id' => $lettered->id]);

    $preview = ScopedListingPreview::resolve(scopedListingPreviewFixtureModifiers($listing));
    expect($preview)->not->toBeNull();
    assert($preview instanceof ScopedListingPreview);

    expect($preview->appliesInput->axisSelections)->toBe([$axis->id => $lettered->id])
        ->and($preview->appliesCaption)->toBe('Version: Hand-lettered')
        ->and($preview->otherInput->axisSelections)->toBe([$axis->id => $blank->id])
        ->and($preview->otherCaption)->toBe('Version: Blank');
});

it('is null when the scoped axis has no sibling to contrast with', function (): void {
    $listing = test()->listing(test()->seller());
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id]);
    $modifier = Modifier::factory()->create(['listing_id' => $listing->id]);
    $modifier->scopes()->create(['option_value_id' => $value->id]);

    expect(ScopedListingPreview::resolve(scopedListingPreviewFixtureModifiers($listing)))->toBeNull();
});

it('names the sibling that would hide the question', function (): void {
    $listing = test()->listing(test()->seller());
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Version']);
    $lettered = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Hand-lettered']);
    OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Blank']);
    $modifier = Modifier::factory()->create(['listing_id' => $listing->id]);
    $modifier->scopes()->create(['option_value_id' => $lettered->id]);
    $loaded = scopedListingPreviewFixtureModifiers($listing)->sole();

    expect(ScopedListingPreview::unaffectedOptionLabel($loaded))->toBe('Blank');
});

it('has no unaffected option label for an unscoped question', function (): void {
    $listing = test()->listing(test()->seller());
    $modifier = Modifier::factory()->create(['listing_id' => $listing->id]);
    $loaded = scopedListingPreviewFixtureModifiers($listing)->sole();

    expect(ScopedListingPreview::unaffectedOptionLabel($loaded))->toBeNull();
});

it('has no unaffected option label when every value on the scoped axis is scoped', function (): void {
    $listing = test()->listing(test()->seller());
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id]);
    $modifier = Modifier::factory()->create(['listing_id' => $listing->id]);
    $modifier->scopes()->create(['option_value_id' => $value->id]);
    $loaded = scopedListingPreviewFixtureModifiers($listing)->sole();

    expect(ScopedListingPreview::unaffectedOptionLabel($loaded))->toBeNull();
});
