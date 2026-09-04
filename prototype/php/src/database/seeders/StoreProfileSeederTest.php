<?php

declare(strict_types=1);

use App\Domain\Store\StoreLinkKind;
use App\Domain\Store\StoreSectionKind;
use App\Models\Seller;
use App\Models\StoreLink;
use App\Models\StoreProfile;
use App\Models\StoreSection;
use Database\Seeders\ConfiguratorArchetypeSeeder;
use Database\Seeders\ListingImageSeeder;
use Database\Seeders\ListingSeeder;
use Database\Seeders\SellerSeeder;
use Database\Seeders\StoreProfileSeeder;
use Database\Seeders\TaxonomySeeder;
use Database\Seeders\WizardingSellerSeeder;

beforeEach(function (): void {
    $this->seed(TaxonomySeeder::class);
    $this->seed(SellerSeeder::class);
    $this->seed(ListingSeeder::class);
    $this->seed(WizardingSellerSeeder::class);
    $this->seed(ConfiguratorArchetypeSeeder::class);
    $this->seed(ListingImageSeeder::class);
});

it('gives every seeded seller a published store at its own address', function (): void {
    $this->seed(StoreProfileSeeder::class);

    $sellers = Seller::query()->with('storeProfile')->get();

    expect($sellers)->toHaveCount(7);

    $sellers->each(function (Seller $seller): void {
        $profile = $seller->storeProfile;

        expect($profile)->not->toBeNull("{$seller->email} has no store")
            ->and($profile?->isPublished())->toBeTrue()
            ->and($profile?->name)->toBe($seller->displayName())
            ->and($profile?->tagline)->not->toBeNull()
            ->and($profile?->location)->not->toBeNull();
    });

    expect(StoreProfile::query()->pluck('slug')->unique())->toHaveCount(7);
});

it('records the address every seeded store answers to', function (): void {
    $this->seed(StoreProfileSeeder::class);

    StoreProfile::query()->get()->each(function (StoreProfile $profile): void {
        $slug = $profile->slugs()->current()->sole();

        expect($slug->slug)->toBe($profile->slug);
    });
});

it('builds every store from a story and a gallery of its own photos', function (): void {
    $this->seed(StoreProfileSeeder::class);

    StoreProfile::query()->with('sections.sectionImages.storeImage')->get()->each(function (StoreProfile $profile): void {
        $kinds = $profile->sections->map(fn (StoreSection $section): StoreSectionKind => $section->kind)->all();
        expect($kinds)->toBe([StoreSectionKind::Story, StoreSectionKind::Gallery]);

        $story = $profile->sections->firstOrFail();
        expect($story->body)->not->toBeNull()
            ->and($story->heading)->not->toBeNull();

        $gallery = $profile->sections->last();
        assert($gallery instanceof StoreSection);

        expect($gallery->sectionImages)->not->toBeEmpty()
            ->and($gallery->sectionImages->pluck('position')->all())->toBe(range(0, $gallery->sectionImages->count() - 1));
    });
});

it('points every store at a portrait and a cover from its own pictures', function (): void {
    $this->seed(StoreProfileSeeder::class);

    StoreProfile::query()->with('portraitImage', 'coverImage')->get()->each(function (StoreProfile $profile): void {
        expect($profile->portraitImage)->not->toBeNull()
            ->and($profile->coverImage)->not->toBeNull()
            ->and($profile->portraitImage?->seller_id)->toBe($profile->seller_id);
    });
});

it('gives every store a website and an Instagram link', function (): void {
    $this->seed(StoreProfileSeeder::class);

    StoreProfile::query()->with('links')->get()->each(function (StoreProfile $profile): void {
        expect($profile->links->map(fn (StoreLink $link): StoreLinkKind => $link->kind)->all())
            ->toBe([StoreLinkKind::Website, StoreLinkKind::Instagram]);
    });
});

it('leaves a seller alone once they already have a store', function (): void {
    $this->seed(StoreProfileSeeder::class);
    $before = StoreProfile::count();

    $this->seed(StoreProfileSeeder::class);

    expect(StoreProfile::count())->toBe($before);
});
