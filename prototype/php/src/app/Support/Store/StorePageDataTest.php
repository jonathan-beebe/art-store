<?php

declare(strict_types=1);

namespace App\Support\Store;

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

    $data = StorePageData::build($profile);

    expect(array_keys($data))->toBe([
        'profile',
        'images',
        'sectionKinds',
        'linkKinds',
        'linksByKind',
        'maxImages',
        'maxSections',
        'maxGalleryImages',
        'maxBodyLength',
        'facts',
    ])
        ->and($data['sectionKinds'])->toBe(StoreSectionKind::cases())
        ->and($data['linkKinds'])->toBe(StoreLinkKind::cases())
        ->and($data['maxImages'])->toBe(StoreProfile::MAX_IMAGES)
        ->and($data['maxSections'])->toBe(StoreSection::MAX_PER_PROFILE)
        ->and($data['maxGalleryImages'])->toBe(StoreSection::MAX_GALLERY_IMAGES)
        ->and($data['maxBodyLength'])->toBe(StoreSection::MAX_BODY_LENGTH)
        ->and($data['facts'])->toBeInstanceOf(StoreFacts::class);

    /** @var StoreProfile $loadedProfile */
    $loadedProfile = $data['profile'];

    expect($loadedProfile->relationLoaded('sections'))->toBeTrue()
        ->and($loadedProfile->relationLoaded('links'))->toBeTrue()
        ->and($loadedProfile->relationLoaded('portraitImage'))->toBeTrue()
        ->and($loadedProfile->relationLoaded('coverImage'))->toBeTrue();
});
