<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Listings\ListingStatus;
use App\Models\Listing;
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

    expect($listings)->toHaveCount(8)
        ->and($listings->every(fn (Listing $listing): bool => $listing->status === ListingStatus::ForSale))->toBeTrue()
        ->and($listings->pluck('medium')->unique()->sort()->values()->all())
        ->toBe(['curio', 'jewelry', 'plant', 'publication']);
});

it('categorizes every listing and carries a Medium attribute matching its legacy medium string', function (): void {
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
            ->and($listing->medium)->not->toBeNull()
            ->and($medium?->propertyValue->label)->toBe(ucfirst((string) $listing->medium));
    }
});

it('changes nothing on a second run', function (): void {
    $this->seed(WizardingSellerSeeder::class);
    $this->seed(WizardingSellerSeeder::class);

    expect(Seller::count())->toBe(2)
        ->and(Listing::count())->toBe(8);
});
