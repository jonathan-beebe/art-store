<?php

declare(strict_types=1);

namespace App\Seller\Store;

use App\Domain\Store\StoreLinkKind;
use App\Domain\Store\StoreSectionKind;
use App\Models\StoreLink;
use App\Models\StoreProfile;
use App\Models\StoreSection;

it('builds the view data the store screen renders from', function (): void {
    $seller = $this->seller();
    $profile = StoreProfile::factory()->create(['seller_id' => $seller->id]);
    StoreSection::factory()->create(['store_profile_id' => $profile->id]);
    StoreLink::factory()->create(['store_profile_id' => $profile->id]);

    $page = StorePageData::build($profile);

    expect($page->sectionKinds)->toBe(StoreSectionKind::cases())
        ->and($page->linkKinds)->toBe(StoreLinkKind::cases())
        ->and($page->maxImages)->toBe(StoreProfile::MAX_IMAGES)
        ->and($page->maxSections)->toBe(StoreSection::MAX_PER_PROFILE)
        ->and($page->maxGalleryImages)->toBe(StoreSection::MAX_GALLERY_IMAGES)
        ->and($page->maxBodyLength)->toBe(StoreSection::MAX_BODY_LENGTH)
        ->and($page->facts)->toBeInstanceOf(StoreFacts::class);

    expect($page->profile->relationLoaded('sections'))->toBeTrue()
        ->and($page->profile->relationLoaded('links'))->toBeTrue()
        ->and($page->profile->relationLoaded('portraitImage'))->toBeTrue()
        ->and($page->profile->relationLoaded('coverImage'))->toBeTrue();
});
