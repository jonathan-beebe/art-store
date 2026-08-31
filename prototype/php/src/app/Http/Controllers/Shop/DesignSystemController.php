<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Domain\Configurator\PriceBreakdownLine;
use App\Models\Listing;
use App\Models\OrderItem;
use App\Support\Configurator\ConfiguratorInput;
use App\Support\Configurator\ConfiguratorPageResolver;
use App\Support\Configurator\ListingConfiguration;
use App\Support\Contrast;
use App\Support\DesignTokens;
use App\Support\Shop\CategoryBrowse;
use App\Support\Shop\FeaturedSubject;
use App\Support\Shop\MediumBrowse;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * `/design-system` — the theme's living reference: the token registry as
 * paint chips and pairings, and the storefront's real atoms, components,
 * and partials rendered against live data, so the page is a place to see
 * and experiment with actual UI rather than a drawing of it.
 */
final class DesignSystemController extends ShopController
{
    /**
     * The text-on-background pairs the palette promises to keep readable:
     * what the relationships section renders and rates.
     */
    private const PAIRINGS = [
        ['ink', 'canvas'], ['ink-muted', 'canvas'], ['ink-faint', 'canvas'],
        ['ink', 'surface'], ['on-accent', 'accent'], ['accent', 'canvas'],
        ['ink', 'accent-soft'], ['danger', 'danger-surface'],
        ['success', 'success-surface'], ['notice', 'notice-surface'],
        ['on-tint', 'tint-2'], ['on-photo', 'photo-scrim'],
    ];

    /**
     * `.bg-photo-scrim` (`resources/css/app.css`) is a gradient, not a flat
     * fill: it reaches this alpha over `--ui-photo-scrim` at the edge
     * `on-photo` text sits against. The token pair alone can't be rated
     * honestly — the photograph behind it is unknown — so this pairing
     * rates `on-photo` against the scrim composited at that alpha over
     * white, the lightest (worst-case) ground a photo can offer.
     */
    private const PHOTO_SCRIM_ALPHA = 0.72;

    private const PHOTO_SCRIM_WORST_CASE_GROUND = '#ffffff';

    public function __invoke(Request $request): View
    {
        $listings = Listing::query()->forSale()->with('seller')
            ->orderByDesc('created_at')->orderByDesc('id')->limit(3)->get();

        // The same predicate ConfiguratorPageResolver::hasConfigurator()
        // checks per listing, asked of the database instead: one query for
        // the first for-sale listing carrying any configurator row, rather
        // than fetching a page of listings and testing each in PHP.
        $configurable = Listing::query()->forSale()->with('seller')
            ->where(function (Builder $query): void {
                $query->whereHas('optionAxes')
                    ->orWhereHas('variants')
                    ->orWhereHas('modifiers')
                    ->orWhereHas('quantityBreaks');
            })
            ->orderByDesc('created_at')->orderByDesc('id')->first();

        $focus = $request->query('focus');

        $configuration = $configurable === null
            ? null
            : ConfiguratorPageResolver::resolve($configurable, ConfiguratorInput::fromQuery($request));

        return view('shop.design-system', [
            'themeName' => DesignTokens::themeName(),
            'pairings' => $this->ratedPairings(),
            'browse' => MediumBrowse::forStorefront(),
            'categories' => CategoryBrowse::forStorefront(),
            'featured' => FeaturedSubject::resolve(),
            'listings' => $listings,
            'configurable' => $configurable,
            'configuration' => $configuration,
            'orderItemPreview' => $this->orderItemPreview($configuration),
            'focusId' => is_string($focus) ? $focus : null,
        ]);
    }

    /**
     * @return list<array{fg: string, bg: string, light: float, dark: float, lightAa: bool, darkAa: bool}>
     */
    private function ratedPairings(): array
    {
        return array_map(function (array $pair): array {
            [$fg, $bg] = $pair;

            $lightBg = $this->backgroundColor($bg, 'light');
            $darkBg = $this->backgroundColor($bg, 'dark');

            $light = Contrast::ratio(DesignTokens::color($fg, 'light'), $lightBg);
            $dark = Contrast::ratio(DesignTokens::color($fg, 'dark'), $darkBg);

            return [
                'fg' => $fg,
                'bg' => $bg,
                'light' => $light,
                'dark' => $dark,
                'lightAa' => Contrast::meetsAa($light),
                'darkAa' => Contrast::meetsAa($dark),
            ];
        }, self::PAIRINGS);
    }

    /**
     * A pairing's background color to rate `fg` against — `photo-scrim`
     * composited over its worst-case ground, since it never sits opaque in
     * the storefront; every other token read straight off the registry.
     */
    private function backgroundColor(string $bg, string $mode): string
    {
        if ($bg !== 'photo-scrim') {
            return DesignTokens::color($bg, $mode);
        }

        return Contrast::compositeOver(DesignTokens::color($bg, $mode), self::PHOTO_SCRIM_ALPHA, self::PHOTO_SCRIM_WORST_CASE_GROUND);
    }

    /**
     * An unsaved order line for the order-item-detail specimen, snapshotted
     * from the configurator specimen's own listing the way
     * {@see \App\Actions\Orders\PlaceOrder} freezes a real one. No seeded
     * order carries a configured item — every seeded purchase predates
     * {@see \Database\Seeders\ConfiguratorArchetypeSeeder} — so this is the
     * specimen's only route to real configurator data rather than none.
     */
    private function orderItemPreview(?ListingConfiguration $configuration): ?OrderItem
    {
        if ($configuration === null || $configuration->variantId === null) {
            return null;
        }

        return new OrderItem([
            'variant_id' => $configuration->variantId,
            'configuration_json' => $configuration->configurationSnapshot,
            'answers_json' => $configuration->answersSnapshot,
            'price_breakdown_json' => array_map(
                fn (PriceBreakdownLine $line): array => ['label' => $line->label, 'cents' => $line->amount->cents],
                $configuration->breakdown->lines,
            ),
        ]);
    }
}
