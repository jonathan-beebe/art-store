<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Seller\ListingView;

/**
 * One entry of the listings header's view switch: the view it links to,
 * the link, and whether it is the current one.
 */
final readonly class ViewLink
{
    public function __construct(
        public ListingView $view,
        public string $href,
        public bool $active,
    ) {}
}
