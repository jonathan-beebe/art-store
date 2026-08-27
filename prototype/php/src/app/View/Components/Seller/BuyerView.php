<?php

declare(strict_types=1);

namespace App\View\Components\Seller;

use App\Models\Listing;
use App\Support\Configurator\ConfiguratorInput;
use App\Support\Configurator\ConfiguratorPageResolver;
use App\Support\Configurator\ListingConfiguration;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The "What buyers see" panel a seller screen shows beside its controls: the
 * storefront configurator for one listing, resolved through the same support
 * path `/art/{slug}` renders from, but never wired to a submittable form —
 * a seller page has no business posting to a shop route. Accepts the buyer's
 * raw choices so a later screen can show the panel under a chosen
 * combination rather than only the listing's defaults.
 */
final class BuyerView extends Component
{
    public readonly bool $hasConfigurator;

    public readonly ?ListingConfiguration $configuration;

    public function __construct(public readonly Listing $listing, public readonly ?ConfiguratorInput $input = null)
    {
        $this->hasConfigurator = ConfiguratorPageResolver::hasConfigurator($listing);
        $this->configuration = $this->hasConfigurator
            ? ConfiguratorPageResolver::resolve($listing, $input ?? ConfiguratorInput::of([], null, [], 1))
            : null;
    }

    public function render(): View
    {
        return view('components.seller.buyer-view');
    }
}
