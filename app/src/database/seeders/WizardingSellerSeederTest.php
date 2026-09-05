<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Listings\ListingStatus;
use App\Models\Listing;
use App\Models\ListingAttribute;
use App\Models\Seller;

beforeEach(function (): void {
    $this->seed(TaxonomySeeder::class);
});

it('seeds two verified sellers with a shop name and a live catalog', function (): void {
    $this->seed(WizardingSellerSeeder::class);

    $sellers = Seller::whereIn('email', [WizardingSellerSeeder::NEVILLE_EMAIL, WizardingSellerSeeder::LUNA_EMAIL])->get();

    expect($sellers)->toHaveCount(2);

    foreach ($sellers as $seller) {
        expect($seller->email_verified_at)->not->toBeNull()
            ->and($seller->shop_name)->not->toBeNull();
    }

    $listings = Listing::whereIn('seller_id', $sellers->pluck('id'))->get();

    $mediumLabels = ListingAttribute::whereIn('listing_id', $listings->pluck('id'))
        ->whereHas('property', fn ($q) => $q->where('name', 'Medium'))
        ->with('propertyValue')
        ->get()
        ->map(fn (ListingAttribute $attribute): string => mb_strtolower($attribute->propertyValue->label))
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect($listings)->toHaveCount(8)
        ->and($listings->every(fn (Listing $listing): bool => $listing->status === ListingStatus::ForSale))->toBeTrue()
        ->and($mediumLabels)->toBe(['curio', 'jewelry', 'plant', 'publication']);
});

it('categorizes every listing and carries a Medium attribute', function (): void {
    $this->seed(WizardingSellerSeeder::class);

    $listings = Listing::whereIn('seller_id', Seller::whereIn('email', [
        WizardingSellerSeeder::NEVILLE_EMAIL, WizardingSellerSeeder::LUNA_EMAIL,
    ])->pluck('id'))->get();

    foreach ($listings as $listing) {
        $medium = $listing->listingAttributes()
            ->with(['property', 'propertyValue'])
            ->get()
            ->firstWhere('property.name', 'Medium');

        expect($listing->category_id)->not->toBeNull()
            ->and($medium?->propertyValue->label)->not->toBeNull();
    }
});

it('changes nothing on a second run', function (): void {
    $this->seed(WizardingSellerSeeder::class);
    $this->seed(WizardingSellerSeeder::class);

    expect(Seller::count())->toBe(2)
        ->and(Listing::count())->toBe(8);
});
