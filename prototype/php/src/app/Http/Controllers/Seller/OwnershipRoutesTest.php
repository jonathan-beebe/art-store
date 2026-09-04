<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Money\Money;
use App\Models\Customer;
use App\Models\DescriptionSection;
use App\Models\FulfillmentFlow;
use App\Models\FulfillmentFlowStep;
use App\Models\ListingFaq;
use App\Models\ListingImage;
use App\Models\Modifier;
use App\Models\ModifierOption;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\QuantityBreak;
use App\Models\StoreImage;
use App\Models\StoreProfile;
use App\Models\StoreSection;
use App\Models\Unit;
use App\Models\Variant;
use App\Notifications\ItemSold;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use LogicException;

/**
 * The ownership analog of {@see GuardedRoutesTest}: every seller-guarded
 * route naming a {listing}, {fulfillment}, {notification}, {customer}, or
 * {section} — including every route nested under one, like {option_axis}
 * or {unit} — answers 404 for a signed-in seller who does not own that
 * resource. Derived from the route table rather than one test per
 * controller, so a route a future controller forgets to guard fails this
 * test rather than shipping unnoticed.
 *
 * `{image}` names two models on two routes — a `ListingImage` nested under
 * `{listing}`, and a bare `StoreImage` — so `$overridesByRouteName` answers
 * the one route the parameter-name map alone would get wrong.
 *
 * One full configurator graph belonging to "Other Studio" supplies a real,
 * resolvable id for every parameter a guarded route can name. A write
 * route's `FormRequest::authorize()` runs before its `rules()`, so a real
 * id proves the refusal is a 404 for the wrong owner rather than a 422 from
 * validation that never got the chance to run.
 */
it('answers 404 for every seller-guarded route naming a resource the signed-in seller does not own', function (): void {
    $other = $this->seller('Other Studio');
    $listing = $this->listing($other);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $optionValue = OptionValue::factory()->create(['axis_id' => $axis->id]);
    $variant = Variant::factory()->create(['listing_id' => $listing->id]);
    $unit = Unit::factory()->create(['variant_id' => $variant->id]);
    $modifier = Modifier::factory()->create(['listing_id' => $listing->id]);
    $modifierOption = ModifierOption::factory()->create(['modifier_id' => $modifier->id]);
    $quantityBreak = QuantityBreak::factory()->create(['listing_id' => $listing->id]);
    $descriptionSection = DescriptionSection::factory()->create(['listing_id' => $listing->id]);
    $faq = ListingFaq::factory()->create(['listing_id' => $listing->id]);
    $image = ListingImage::factory()->create(['listing_id' => $listing->id]);
    $fulfillment = $this->paidFulfillmentFor($other);
    $flow = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $other->id]);
    $step = FulfillmentFlowStep::factory()->of($flow, 0)->create();
    $other->notify(new ItemSold('ord_00000000000000000000000099', Money::fromCents(9000)));
    $notification = $other->notifications()->firstOrFail();
    $customer = Customer::factory()->create();
    $this->paidFulfillmentFor($other, $customer);
    $profile = StoreProfile::factory()->create(['seller_id' => $other->id]);
    $section = StoreSection::factory()->create(['store_profile_id' => $profile->id]);
    $storeImage = StoreImage::factory()->create(['store_profile_id' => $profile->id]);

    /** @var array<string, string> $ownedByOther */
    $ownedByOther = [
        'listing' => $listing->id,
        'option_axis' => $axis->id,
        'option_value' => $optionValue->id,
        'variant' => $variant->id,
        'unit' => $unit->id,
        'modifier' => $modifier->id,
        'option' => $modifierOption->id,
        'quantity_break' => $quantityBreak->id,
        'description_section' => $descriptionSection->id,
        'faq' => $faq->id,
        'image' => $image->id,
        'fulfillment' => $fulfillment->id,
        'step' => $step->id,
        'notification' => $notification->id,
        'customer' => $customer->id,
        'section' => $section->id,
    ];

    /** @var array<string, array<string, string>> $overridesByRouteName */
    $overridesByRouteName = [
        'seller.store.images.destroy' => ['image' => $storeImage->id],
    ];

    $routes = collect(RouteFacade::getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => in_array('auth.seller', $route->gatherMiddleware(), true))
        ->filter(fn (Route $route): bool => collect($route->parameterNames())->intersect(array_keys($ownedByOther))->isNotEmpty());

    expect($routes)->not->toBeEmpty();

    $seller = $this->seller();

    foreach ($routes as $route) {
        /** @var list<string> $parameterNames */
        $parameterNames = $route->parameterNames();

        /** @var list<string> $methods */
        $methods = $route->methods();

        $overrides = $overridesByRouteName[$route->getName() ?? ''] ?? [];

        $uri = $route->uri();
        foreach ($parameterNames as $parameter) {
            $value = $overrides[$parameter]
                ?? $ownedByOther[$parameter]
                ?? throw new LogicException("OwnershipRoutesTest has no owned-by-another-seller value for route parameter \"{$parameter}\" on \"{$route->uri()}\". Add one to the resource graph above.");
            $uri = (string) preg_replace('/\{'.preg_quote($parameter, '/').'\??\}/', $value, $uri);
        }
        $method = collect($methods)->firstOrFail(fn (string $method): bool => $method !== 'HEAD');

        $response = $this->actingAs($seller, 'seller')->call($method, '/'.ltrim($uri, '/'));

        expect($response->status())
            ->toBe(404, "Expected {$method} /{$uri} to answer 404 for a seller who does not own it, got {$response->status()}.");
    }
});
