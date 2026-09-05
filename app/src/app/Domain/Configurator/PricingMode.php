<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

/**
 * How an axis's options are priced, chosen once when the axis is created
 * (`docs/item-configurator.md` §3): `Standalone` options each carry their own
 * absolute price and replace the listing's base price when selected;
 * `AddOn` options carry a signed surcharge on top of it. Never rendered
 * seller- or buyer-facing by these words — the craft vocabulary ("each
 * option priced on its own" / "adds to your price") lives in the views.
 */
enum PricingMode: string
{
    case Standalone = 'standalone';
    case AddOn = 'add_on';

    public function isStandalone(): bool
    {
        return $this === self::Standalone;
    }
}
