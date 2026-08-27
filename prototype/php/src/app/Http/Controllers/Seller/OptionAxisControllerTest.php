<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Configurator\PricingMode;
use App\Domain\RateLimiting\RateLimitValue;
use App\Models\Category;
use App\Models\CategoryProperty;
use App\Models\Listing;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\Property;
use App\Models\PropertyValue;
use App\Models\Seller;
use App\Models\Variant;
use App\Models\VariantOption;
use Database\Seeders\ConfiguratorArchetypeSeeder;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Support\Facades\Config;

it('lists the listing’s axes with their options', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Metal']);
    OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Gold']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/option-axes");

    $response->assertOk();
    $response->assertSee('Metal');
    $response->assertSee('Gold');
});

it('offers only the current categorys usable-as-axis properties in the picker', function (): void {
    $seller = $this->seller();
    $category = Category::factory()->create();
    $axisProperty = Property::factory()->create(['name' => 'Ring Size']);
    CategoryProperty::factory()->create(['category_id' => $category->id, 'property_id' => $axisProperty->id, 'usable_as_axis' => true, 'usable_as_attribute' => false]);
    $attributeOnlyProperty = Property::factory()->create(['name' => 'Material Only']);
    CategoryProperty::factory()->create(['category_id' => $category->id, 'property_id' => $attributeOnlyProperty->id, 'usable_as_axis' => false, 'usable_as_attribute' => true]);
    $elsewhereProperty = Property::factory()->create(['name' => 'Elsewhere Property']);
    $listing = $this->listing($seller, ['category_id' => $category->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/option-axes?mode=add_on");

    $response->assertSee('Ring Size');
    $response->assertDontSee('Material Only');
    $response->assertDontSee('Elsewhere Property');
});

it('offers no catalog properties for an uncategorized listing', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    Property::factory()->create(['name' => 'Somewhere Property']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/option-axes");

    $response->assertDontSee('Somewhere Property');
});

it('refuses another sellers listing axes page', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));

    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/listings/{$listing->id}/option-axes");

    $response->assertNotFound();
});

it('adds a custom axis', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes", [
        'name' => 'Engraving Placement',
        'position' => 0,
    ]);

    $response->assertRedirect(route('seller.listings.option-axes.index', $listing));
    $axis = OptionAxis::where('listing_id', $listing->id)->sole();
    expect($axis->name)->toBe('Engraving Placement')
        ->and($axis->property_id)->toBeNull();
});

it('adds an axis backed by a catalog property', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $property = Property::factory()->create();

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes", [
        'name' => 'Metal',
        'property_id' => $property->id,
        'position' => 1,
    ]);

    expect(OptionAxis::where('listing_id', $listing->id)->sole()->property_id)->toBe($property->id);
});

it('pre-fills a catalog axis’s option values from the property’s own catalog values', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $property = Property::factory()->create();
    $gold = PropertyValue::factory()->create(['property_id' => $property->id, 'label' => 'Gold', 'position' => 0]);
    $silver = PropertyValue::factory()->create(['property_id' => $property->id, 'label' => 'Silver', 'position' => 1]);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes", [
        'name' => 'Metal',
        'property_id' => $property->id,
        'position' => 0,
    ]);

    $axis = OptionAxis::where('listing_id', $listing->id)->sole();

    expect($axis->optionValues()->count())->toBe(2);

    $goldValue = $axis->optionValues()->where('label', 'Gold')->sole();
    $silverValue = $axis->optionValues()->where('label', 'Silver')->sole();

    expect($goldValue->property_value_id)->toBe($gold->id)
        ->and($goldValue->is_default)->toBeTrue()
        ->and($silverValue->property_value_id)->toBe($silver->id)
        ->and($silverValue->is_default)->toBeFalse();
});

it('adds no option values for a custom, non-catalog axis', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes", [
        'name' => 'Engraving Placement',
        'position' => 0,
    ]);

    expect(OptionAxis::where('listing_id', $listing->id)->sole()->optionValues()->count())->toBe(0);
});

it('lists the picker’s catalog properties before the "Something else…" custom-choice link', function (): void {
    $seller = $this->seller();
    $category = Category::factory()->create();
    $property = Property::factory()->create(['name' => 'Ring Size']);
    CategoryProperty::factory()->create(['category_id' => $category->id, 'property_id' => $property->id, 'usable_as_axis' => true]);
    $listing = $this->listing($seller, ['category_id' => $category->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/option-axes?mode=add_on");

    $content = $response->getContent() ?: '';
    $ringSizePosition = strpos($content, 'Ring Size');
    $somethingElsePosition = strpos($content, 'Something else');

    expect($ringSizePosition)->not->toBeFalse()
        ->and($somethingElsePosition)->not->toBeFalse()
        ->and((int) $ringSizePosition)->toBeLessThan((int) $somethingElsePosition);
});

it('refuses adding an axis to another sellers listing', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));

    $response = $this->actingAs($this->seller(), 'seller')->post("/seller/listings/{$listing->id}/option-axes", [
        'name' => 'Metal',
        'position' => 0,
    ]);

    $response->assertNotFound();
    expect(OptionAxis::count())->toBe(0);
});

it('updates an axis', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Metal']);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/option-axes/{$axis->id}", [
        'name' => 'Finish',
        'position' => 2,
    ]);

    $response->assertRedirect(route('seller.listings.option-axes.index', $listing));
    expect($axis->fresh()?->name)->toBe('Finish')
        ->and($axis->fresh()?->position)->toBe(2);
});

it('answers not found updating an axis from another listing', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $otherListing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $otherListing->id]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/option-axes/{$axis->id}", [
        'name' => 'Finish',
        'position' => 0,
    ]);

    $response->assertNotFound();
});

it('removes an axis no variant references', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/option-axes/{$axis->id}");

    $response->assertRedirect(route('seller.listings.option-axes.index', $listing));
    expect(OptionAxis::find($axis->id))->toBeNull();
});

it('refuses to remove an axis a variant still selects a value from', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id]);
    $variant = Variant::factory()->create(['listing_id' => $listing->id]);
    VariantOption::factory()->create(['variant_id' => $variant->id, 'axis_id' => $axis->id, 'option_value_id' => $value->id]);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/option-axes/{$axis->id}");

    $response->assertSessionHasErrors();
    expect(OptionAxis::find($axis->id))->not->toBeNull();
});

it('refuses to remove another sellers axis', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($this->seller(), 'seller')->delete("/seller/listings/{$listing->id}/option-axes/{$axis->id}");

    $response->assertNotFound();
    expect(OptionAxis::find($axis->id))->not->toBeNull();
});

it('trips the listing-write limit adding an axis, re-rendering the index with nothing saved', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes", ['name' => 'First', 'position' => 0]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes", ['name' => 'Second', 'position' => 1]);

    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
    expect(OptionAxis::where('listing_id', $listing->id)->count())->toBe(1);
});

it('trips the listing-write limit updating an axis', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Old']);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes", ['name' => 'Consumes the budget', 'position' => 0]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/option-axes/{$axis->id}", ['name' => 'New', 'position' => 0]);

    $response->assertStatus(429);
    expect($axis->fresh()?->name)->toBe('Old');
});

it('trips the listing-write limit removing an axis', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes", ['name' => 'Consumes the budget', 'position' => 0]);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/option-axes/{$axis->id}");

    $response->assertStatus(429);
    expect(OptionAxis::find($axis->id))->not->toBeNull();
});

it('shows an empty-state invitation when a listing has no choices yet', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/option-axes");

    $response->assertSee('No choices yet. Add one below');
});

it('renders a choice with no options yet without the price-neutral hint', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Engraving Placement']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/option-axes");

    $response->assertSee('Engraving Placement');
    $response->assertDontSee('A choice with no price differences');
});

it('shows the "Something else…" link only when there is a catalog property to lead with', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/option-axes?mode=add_on");

    $response->assertDontSee('Something else');
    $response->assertSee('Choice name');
});

it('reveals the custom-choice name field when asked for "Something else…"', function (): void {
    $seller = $this->seller();
    $category = Category::factory()->create();
    $property = Property::factory()->create(['name' => 'Ring Size']);
    CategoryProperty::factory()->create(['category_id' => $category->id, 'property_id' => $property->id, 'usable_as_axis' => true]);
    $listing = $this->listing($seller, ['category_id' => $category->id]);

    $collapsed = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/option-axes?mode=add_on");
    $collapsed->assertDontSee('Choice name');
    $collapsed->assertSee('Something else');

    $expanded = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/option-axes?mode=add_on&choice=custom");
    $expanded->assertSee('Choice name');
});

it('adds a catalog choice from its "Add another choice" button', function (): void {
    $seller = $this->seller();
    $category = Category::factory()->create();
    $property = Property::factory()->create(['name' => 'Wax type']);
    CategoryProperty::factory()->create(['category_id' => $category->id, 'property_id' => $property->id, 'usable_as_axis' => true]);
    $listing = $this->listing($seller, ['category_id' => $category->id]);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes", [
        'name' => 'Wax type',
        'property_id' => $property->id,
        'position' => 0,
    ]);

    expect(OptionAxis::where('listing_id', $listing->id)->sole()->property_id)->toBe($property->id);
});

it('C5 shows the honest per-listing shipping-timeline note', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/option-axes");

    $response->assertSee('Every option ships on this listing');
    $response->assertSee('silver ships tomorrow, gold takes 3 weeks');
});

it('A1 shows per-option buyers-pay chips and an adds-to-your-price pill for each add-on choice', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['price_cents' => 2400]);

    $size = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Size', 'position' => 0]);
    OptionValue::factory()->create(['axis_id' => $size->id, 'label' => '8 oz', 'is_default' => true, 'position' => 0]);
    OptionValue::factory()->create(['axis_id' => $size->id, 'label' => '12 oz', 'surcharge_cents' => 600, 'position' => 1]);
    OptionValue::factory()->create(['axis_id' => $size->id, 'label' => '16 oz', 'surcharge_cents' => 1000, 'position' => 2]);

    $scent = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Scent', 'position' => 1]);
    OptionValue::factory()->create(['axis_id' => $scent->id, 'label' => 'Sea Salt', 'is_default' => true, 'position' => 0]);
    OptionValue::factory()->create(['axis_id' => $scent->id, 'label' => 'Fig', 'position' => 1]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/option-axes");

    expect(substr_count($response->getContent() ?: '', 'adds to your price'))->toBe(2);
    $response->assertSee('buyers pay $24.00');
    $response->assertSee('buyers pay $30.00');
    $response->assertSee('buyers pay $34.00');
    $response->assertSee('(+$6.00)', escape: false);
    $response->assertSeeInOrder(['Total', '$24.00']);
});

it('DSGN-002 shows the two pricing-mode buttons before any choice has been started', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/option-axes");

    $response->assertSee('Each option priced on its own');
    $response->assertSee('Options add to your price');
    $response->assertSee('Pick how its options get priced — you can\'t change this after adding the first option, so choose the one that matches how you actually price it.', escape: false);
    $response->assertDontSee('Choice name');
});

it('DSGN-002 threads the chosen pricing mode into the catalog-or-custom picker and the created axis', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $withMode = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/option-axes?mode=standalone");

    $withMode->assertSee('Choice name');
    $withMode->assertSee('name="pricing_mode" value="standalone"', false);
    $withMode->assertSee('each option priced on its own');

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes", [
        'name' => 'Size',
        'position' => 0,
        'pricing_mode' => 'standalone',
    ]);

    $axis = OptionAxis::where('listing_id', $listing->id)->sole();
    expect($axis->pricing_mode)->toBe(PricingMode::Standalone);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values", [
        'label' => '8x10',
        'price' => '18.00',
        'position' => 0,
    ]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/option-axes");

    $response->assertSee('name="price"', false);
    $response->assertSee('value="18.00"', false);
    expect(substr_count($response->getContent() ?: '', 'buyers pay'))->toBe(0);
});

it('DSGN-002 skips catalog prefill for a standalone axis, since no price can be inferred from the catalog', function (): void {
    $seller = $this->seller();
    $category = Category::factory()->create();
    $property = Property::factory()->create(['name' => 'Wood Species']);
    CategoryProperty::factory()->create(['category_id' => $category->id, 'property_id' => $property->id, 'usable_as_axis' => true]);
    PropertyValue::factory()->create(['property_id' => $property->id, 'label' => 'Walnut', 'position' => 0]);
    $listing = $this->listing($seller, ['category_id' => $category->id]);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes", [
        'name' => 'Wood Species',
        'property_id' => $property->id,
        'position' => 0,
        'pricing_mode' => 'standalone',
    ]);

    $axis = OptionAxis::where('listing_id', $listing->id)->sole();
    expect($axis->pricing_mode)->toBe(PricingMode::Standalone)
        ->and($axis->property_id)->toBe($property->id)
        ->and($axis->optionValues()->count())->toBe(0);
});

it('DSGN-002 renders the standalone footer hint and the add-on footer hint once options price differently', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['price_cents' => 1800]);

    $size = OptionAxis::factory()->standalone()->create(['listing_id' => $listing->id, 'name' => 'Size']);
    OptionValue::factory()->priced(1800)->create(['axis_id' => $size->id, 'label' => '8x10', 'is_default' => true]);

    $frame = OptionAxis::factory()->addOn()->create(['listing_id' => $listing->id, 'name' => 'Frame']);
    OptionValue::factory()->create(['axis_id' => $frame->id, 'label' => 'Unframed', 'surcharge_cents' => 0, 'is_default' => true]);
    OptionValue::factory()->create(['axis_id' => $frame->id, 'label' => 'Black frame', 'surcharge_cents' => 3200]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/option-axes");

    $response->assertSee('No option is the "base" here', false);
    $response->assertSee('Buyers pay your item\'s price, plus whatever Frame adds on top.', false);
});

it('DSGN-002 shows the required-price message inline on the choice’s own option row', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->standalone()->create(['listing_id' => $listing->id, 'name' => 'Size']);
    $value = OptionValue::factory()->priced(1800)->create(['axis_id' => $axis->id, 'label' => '8x10']);

    $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values/{$value->id}", [
        'label' => '8x10',
        'price' => '',
        'position' => 0,
    ]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/option-axes");

    $response->assertSee('This choice prices each option on its own — give it a price.', false);
});

it('DSGN-002 renders the seeded Sunset Ridge listing’s standalone Size and add-on Frame choices correctly', function (): void {
    $this->seed(TaxonomySeeder::class);
    $this->seed(ConfiguratorArchetypeSeeder::class);
    $seller = Seller::where('email', ConfiguratorArchetypeSeeder::EMAIL)->sole();
    $listing = Listing::where('title', 'Sunset Ridge, Fine Art Print')->sole();

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/option-axes");

    $response->assertOk();
    $response->assertSee('each option priced on its own');
    $response->assertSee('adds to your price');
    $response->assertSeeInOrder(['8x10', '11x14', '16x20']);
    expect(substr_count($response->getContent() ?: '', 'buyers pay'))->toBe(2);
    // Three existing options' price fields, plus the standalone card's own
    // "Add option" price field at the bottom.
    expect(substr_count($response->getContent() ?: '', 'name="price"'))->toBe(4);
});

it('A2 keeps the same price difference on a size option however the other choice defaults', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['price_cents' => 2400]);

    $size = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Size', 'position' => 0]);
    OptionValue::factory()->create(['axis_id' => $size->id, 'label' => '8 oz', 'is_default' => true, 'position' => 0]);
    OptionValue::factory()->create(['axis_id' => $size->id, 'label' => '12 oz', 'surcharge_cents' => 600, 'position' => 1]);

    $scent = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Scent', 'position' => 1]);
    $seaSalt = OptionValue::factory()->create(['axis_id' => $scent->id, 'label' => 'Sea Salt', 'is_default' => true, 'position' => 0]);
    $fig = OptionValue::factory()->create(['axis_id' => $scent->id, 'label' => 'Fig', 'position' => 1]);

    $firstResponse = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/option-axes");
    $firstResponse->assertSee('+$6.00');

    $seaSalt->update(['is_default' => false]);
    $fig->update(['is_default' => true]);

    $secondResponse = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/option-axes");
    $secondResponse->assertSee('+$6.00');
});

it('A4 renders four choices as their own cards with four selects in the buyer panel', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    foreach (['Size', 'Scent', 'Color', 'Wax type'] as $index => $name) {
        $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => $name, 'position' => $index]);
        OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'One', 'is_default' => true, 'position' => 0]);
        OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Two', 'position' => 1]);
    }

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/option-axes");

    $response->assertSee('Size');
    $response->assertSee('Scent');
    $response->assertSee('Color');
    $response->assertSee('Wax type');
    expect(substr_count($response->getContent() ?: '', '<select'))->toBe(4);
});

it('A12 keeps pet, pose, and output as three separate choices, not one crammed dropdown', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    foreach (['Pet', 'Pose', 'Output'] as $index => $name) {
        $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => $name, 'position' => $index]);
        OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'One', 'is_default' => true, 'position' => 0]);
        OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Two', 'position' => 1]);
    }

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/option-axes");

    $response->assertSee('Pet');
    $response->assertSee('Pose');
    $response->assertSee('Output');
    expect(substr_count($response->getContent() ?: '', '<select'))->toBe(3);
});

it('A9 shows the option delta at the point of choice in the buyer panel', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['price_cents' => 2400]);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Size']);
    OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => '8 oz', 'is_default' => true, 'position' => 0]);
    OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => '12 oz', 'surcharge_cents' => 600, 'position' => 1]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/option-axes");

    $response->assertSee('12 oz (+$6.00)', escape: false);
});
