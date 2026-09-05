<?php

declare(strict_types=1);

namespace App\View\Components\Seller;

use App\Configurator\ConfiguratorInput;
use App\Configurator\ConfiguratorPageResolver;
use App\Configurator\ListingConfiguration;
use App\Models\Listing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\View\Component;

/**
 * The "What buyers see" panel a seller screen shows beside its controls —
 * the same view model and the same rendering partials `/art/{slug}` uses
 * (IMPRV-015), scaled to the 380px column and with an inert Add to cart, so
 * a rendering-rule change lands on both surfaces from one edit. Live by
 * default: its form round-trips GET params on the seller screen's own URL,
 * the same way the shop page round-trips on its own. `$interactive = false`
 * renders a disabled, form-less reading instead — for a panel pinned to a
 * configuration the request can never influence (the modifier scope demo's
 * "applies" / "other" pair), where a real form would accept input it then
 * silently discards.
 */
final class BuyerView extends Component
{
    public readonly bool $hasConfigurator;

    public readonly ?ListingConfiguration $configuration;

    public readonly string $refreshUrl;

    public readonly ?string $focusId;

    public function __construct(
        public readonly Listing $listing,
        public readonly ?ConfiguratorInput $input = null,
        public readonly ?string $caption = null,
        public readonly bool $interactive = true,
    ) {
        $listing->loadMissing([
            'images' => fn (Relation $query): Relation => $query->orderBy('position'),
            'descriptionSections' => fn (Relation $query): Relation => $query->orderBy('position'),
        ]);

        $request = request();

        $this->hasConfigurator = ConfiguratorPageResolver::hasConfigurator($listing);
        $this->configuration = $this->hasConfigurator
            ? ConfiguratorPageResolver::resolve($listing, $input ?? ConfiguratorInput::fromQuery($request))
            : null;
        $this->refreshUrl = $request->url();

        $focus = $request->query('focus');
        $this->focusId = is_string($focus) ? $focus : null;
    }

    public function render(): View
    {
        return view('components.seller.buyer-view');
    }
}
