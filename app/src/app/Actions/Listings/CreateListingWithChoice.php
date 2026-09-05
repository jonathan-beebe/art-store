<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Actions\Configurator\AddOptionValue;
use App\Actions\Configurator\CreateOptionAxis;
use App\Actions\Configurator\GenerateVariants;
use App\Domain\Listings\ListingCreationChoice;
use App\Domain\Listings\ListingDraft;
use App\Models\Listing;
use App\Models\Seller;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * The create screen's whole unit of work: the listing, and when the shape
 * adds one, its first choice with every option and the variant per
 * combination. One transaction, so a failure past the listing row leaves
 * no listing behind with a choice and no variants. Each step tells its own
 * story, the same lines the edit screens write for the same writes.
 */
final readonly class CreateListingWithChoice
{
    public function __construct(
        private CreateListing $createListing,
        private CreateOptionAxis $createOptionAxis,
        private AddOptionValue $addOptionValue,
        private GenerateVariants $generateVariants,
    ) {}

    public function __invoke(Seller $seller, ListingDraft $draft, ?ListingCreationChoice $choice, ?UploadedFile $image = null): Listing
    {
        return DB::transaction(function () use ($seller, $draft, $choice, $image): Listing {
            $listing = ($this->createListing)($seller, $draft, $image);

            if ($choice !== null) {
                $this->addChoice($listing, $choice);
            }

            return $listing;
        });
    }

    /**
     * The first row is the default, which is what `ListingPriceSync` reads
     * back onto `listings.price_cents` for a `standalone` choice.
     */
    private function addChoice(Listing $listing, ListingCreationChoice $choice): void
    {
        $axis = ($this->createOptionAxis)($listing, $choice->name, null, 0, $choice->pricingMode);

        foreach ($choice->rows as $index => $row) {
            ($this->addOptionValue)($axis, $row['label'], $row['cents'], $index === 0, $index, null, $choice->priceCentsOf($row['cents']));
        }

        ($this->generateVariants)($listing);
    }
}
