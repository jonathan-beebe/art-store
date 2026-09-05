<?php

declare(strict_types=1);

namespace App\Seller\Store;

use App\Domain\Store\StoreLinkKind;
use App\Domain\Store\StoreSectionKind;
use App\Models\StoreLink;
use App\Models\StoreProfile;
use App\Models\StoreSection;

/**
 * The view data the Store screen renders from: the profile with everything
 * the buyer preview reads eagerly loaded, the pictures the seller can place,
 * and the vocabularies the form's controls are built out of.
 */
final class StorePageData
{
    private function __construct() {} // @codeCoverageIgnore

    public static function build(StoreProfile $profile): StorePage
    {
        $profile->load([
            'seller',
            'portraitImage',
            'coverImage',
            'links',
            'sections.sectionImages.storeImage',
        ]);

        return new StorePage(
            profile: $profile,
            images: $profile->images()->orderBy('created_at')->orderBy('id')->get(),
            sectionKinds: StoreSectionKind::cases(),
            linkKinds: StoreLinkKind::cases(),
            linksByKind: $profile->links->keyBy(fn (StoreLink $link): string => $link->kind->value),
            maxImages: StoreProfile::MAX_IMAGES,
            maxSections: StoreSection::MAX_PER_PROFILE,
            maxGalleryImages: StoreSection::MAX_GALLERY_IMAGES,
            maxBodyLength: StoreSection::MAX_BODY_LENGTH,
            facts: StoreFactsReader::for($profile),
        );
    }
}
