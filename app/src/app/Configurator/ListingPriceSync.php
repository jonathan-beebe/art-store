<?php

declare(strict_types=1);

namespace App\Configurator;

use App\Domain\Configurator\PricingMode;
use App\Models\Listing;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use Illuminate\Support\Collection;

/**
 * Keeps `listings.price_cents` equal to the default configuration's
 * standalone sum whenever the listing carries a `standalone` axis — the
 * price the listing page opens at, and what a storefront card reads
 * (`docs/item-configurator.md` §3). Called from the option-axis and
 * option-value Actions after any write that could move that sum: an axis
 * gaining or losing `standalone` mode, an option's price changing, an
 * option or axis being added or removed.
 *
 * A listing with no `standalone` axis is left alone — `price_cents` stays
 * the seller-edited value it always was, including right after a seller
 * removes their last `standalone` axis: nothing here un-derives it back to
 * an earlier seller-typed price, it simply stops being written to.
 */
final class ListingPriceSync
{
    private function __construct() {} // @codeCoverageIgnore

    public static function sync(Listing $listing): void
    {
        $axes = $listing->optionAxes()->with('optionValues')->orderBy('position')->get();

        if (! $axes->contains(fn (OptionAxis $axis): bool => $axis->pricing_mode === PricingMode::Standalone)) {
            return;
        }

        $sum = self::defaultConfigurationStandaloneSum($axes);

        if ($sum !== $listing->price_cents) {
            $listing->update(['price_cents' => $sum]);
        }
    }

    /**
     * @param  Collection<int, OptionAxis>  $axes
     */
    private static function defaultConfigurationStandaloneSum(Collection $axes): int
    {
        $sum = 0;

        foreach ($axes as $axis) {
            if ($axis->pricing_mode !== PricingMode::Standalone) {
                continue;
            }

            $values = $axis->optionValues->sortBy('position')->values();
            $default = $values->firstWhere('is_default', true) ?? $values->first();

            if ($default instanceof OptionValue) {
                $sum += $default->price_cents ?? 0;
            }
        }

        return $sum;
    }
}
