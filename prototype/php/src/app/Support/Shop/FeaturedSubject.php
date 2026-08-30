<?php

declare(strict_types=1);

namespace App\Support\Shop;

use App\Models\Category;
use App\Models\Listing;
use Illuminate\Support\Str;

/**
 * The home page's featured band (DSGN-007): one listing or one category,
 * named by hand in `config('storefront.featured')` and resolved fresh on
 * every request. `resolve()` answers null — the band's honest "nothing to
 * show" — when the config names a listing no longer for sale, or a category
 * that no longer exists, is not browsable, or carries no for-sale listing;
 * the page renders no broken card and no substitute for either failure.
 */
final readonly class FeaturedSubject
{
    private function __construct(
        public string $title,
        public string $description,
        public string $imageUrl,
        public ?string $price,
        public ?string $byline,
        public string $ctaHref,
        public string $ctaLabel,
    ) {}

    public static function resolve(): ?self
    {
        $config = config('storefront.featured');
        $value = (string) $config['value'];

        return match ($config['type']) {
            'listing' => self::resolveListing($value),
            'category' => self::resolveCategory($value),
            default => null,
        };
    }

    private static function resolveListing(string $slug): ?self
    {
        $listing = Listing::query()->forSale()->with('seller')->where('slug', $slug)->first();

        if ($listing === null) {
            return null;
        }

        return new self(
            title: $listing->title,
            description: $listing->description ?? '',
            imageUrl: $listing->imageUrl(),
            price: $listing->price()->format(),
            byline: $listing->seller->displayName(),
            ctaHref: route('shop.listing', $listing),
            ctaLabel: 'See this piece',
        );
    }

    private static function resolveCategory(string $path): ?self
    {
        $category = Category::query()
            ->where('path', '/'.trim($path, '/').'/')
            ->where('browsable', true)
            ->first();

        if ($category === null) {
            return null;
        }

        $inCategory = Listing::query()->forSale()->ofCategoryPathPrefix($category->path);
        $count = (clone $inCategory)->count();

        if ($count === 0) {
            return null;
        }

        $cover = (clone $inCategory)
            ->withCount('favorites')
            ->orderByDesc('favorites_count')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->firstOrFail();

        return new self(
            title: $category->name,
            description: sprintf('%d %s waiting to be discovered.', $count, Str::plural('piece', $count)),
            imageUrl: $cover->imageUrl(),
            price: null,
            byline: null,
            ctaHref: route('shop.browse', ['categoryPath' => $category->browsePath()]),
            ctaLabel: 'Browse '.$category->name,
        );
    }
}
