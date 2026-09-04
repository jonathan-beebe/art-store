<?php

declare(strict_types=1);

namespace App\Seller\Store;

use App\Domain\Store\StoreLinkKind;
use App\Domain\Store\StoreSectionKind;
use App\Models\StoreImage;
use App\Models\StoreLink;
use App\Models\StoreProfile;
use Illuminate\Database\Eloquent\Collection;

/**
 * The view data the Store screen renders from: the profile with everything
 * the buyer preview reads eagerly loaded, the pictures the seller can place,
 * and the vocabularies the form's controls are built out of.
 */
final readonly class StorePage
{
    /**
     * @param  Collection<int, StoreImage>  $images
     * @param  list<StoreSectionKind>  $sectionKinds
     * @param  list<StoreLinkKind>  $linkKinds
     * @param  Collection<string, StoreLink>  $linksByKind
     */
    public function __construct(
        public StoreProfile $profile,
        public Collection $images,
        public array $sectionKinds,
        public array $linkKinds,
        public Collection $linksByKind,
        public int $maxImages,
        public int $maxSections,
        public int $maxGalleryImages,
        public int $maxBodyLength,
        public StoreFacts $facts,
    ) {}
}
