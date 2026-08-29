<?php

declare(strict_types=1);

use App\Models\Listing;
use Database\Seeders\ConfiguratorArchetypeSeeder;
use Database\Seeders\ListingImageSeeder;
use Database\Seeders\ListingSeeder;
use Database\Seeders\SellerSeeder;
use Database\Seeders\TaxonomySeeder;
use Database\Seeders\WizardingSellerSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(TaxonomySeeder::class);
    $this->seed(SellerSeeder::class);
    $this->seed(ListingSeeder::class);
    $this->seed(WizardingSellerSeeder::class);
    $this->seed(ConfiguratorArchetypeSeeder::class);
});

it('gives every seeded listing a cover photo that exists on the public disk', function (): void {
    $this->seed(ListingImageSeeder::class);

    Listing::query()->with('images')->get()->each(function (Listing $listing): void {
        expect($listing->images)->not->toBeEmpty("{$listing->title} has no seeded image");
        expect($listing->images->min('position'))->toBe(0);

        $listing->images->each(function ($image): void {
            expect(Storage::disk('public')->exists($image->path))->toBeTrue("missing file {$image->path}");
        });
    });
});

it('builds a multi-photo gallery for the mug and the scarf throw, in order', function (): void {
    $this->seed(ListingImageSeeder::class);

    $mug = Listing::where('title', 'Three Broomsticks Stoneware Mug')->sole();
    $throw = Listing::where('title', 'House Scarf Throw, Scarlet and Gold')->sole();

    expect($mug->images()->orderBy('position')->pluck('position')->all())->toBe([0, 1, 2])
        ->and($throw->images()->count())->toBe(2);
});

it('leaves a listing alone once it already has images', function (): void {
    $this->seed(ListingImageSeeder::class);
    $before = App\Models\ListingImage::count();

    $this->seed(ListingImageSeeder::class);

    expect(App\Models\ListingImage::count())->toBe($before);
});
