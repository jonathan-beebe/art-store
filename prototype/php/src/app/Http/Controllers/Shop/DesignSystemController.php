<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Models\Listing;
use App\Support\Configurator\ConfiguratorInput;
use App\Support\Configurator\ConfiguratorPageResolver;
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
        ['on-tint', 'tint-2'],
    ];

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

        return view('shop.design-system', [
            'themeName' => DesignTokens::themeName(),
            'pairings' => $this->ratedPairings(),
            'browse' => MediumBrowse::forStorefront(),
            'categories' => CategoryBrowse::forStorefront(),
            'featured' => FeaturedSubject::resolve(),
            'listings' => $listings,
            'configurable' => $configurable,
            'configuration' => $configurable === null
                ? null
                : ConfiguratorPageResolver::resolve($configurable, ConfiguratorInput::fromQuery($request)),
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

            $light = Contrast::ratio(DesignTokens::color($fg, 'light'), DesignTokens::color($bg, 'light'));
            $dark = Contrast::ratio(DesignTokens::color($fg, 'dark'), DesignTokens::color($bg, 'dark'));

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
}
