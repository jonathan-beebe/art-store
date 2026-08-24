<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Domain\Shop\ListingSearch;
use App\Models\Listing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class StorefrontController extends ShopController
{
    private const LISTINGS_PER_PAGE = 12;

    public function __invoke(Request $request): View
    {
        $search = ListingSearch::fromInput(
            $this->submitted($request, 'q'),
            $this->submitted($request, 'medium'),
        );

        return view('shop.home', [
            'search' => $search,
            'media' => $this->mediaForSale(),
            'listings' => $this->matching($search)->paginate(self::LISTINGS_PER_PAGE)->withQueryString(),
        ]);
    }

    /**
     * @return Builder<Listing>
     */
    private function matching(ListingSearch $search): Builder
    {
        $listings = Listing::query()->forSale()->with('seller')->orderByDesc('created_at')->orderByDesc('id');

        if ($search->hasTerm()) {
            $pattern = $search->likePattern();

            $listings->where(fn (Builder $match): Builder => $match
                ->where('title', 'like', $pattern)
                ->orWhere('description', 'like', $pattern)
                ->orWhere('medium', 'like', $pattern));
        }

        if ($search->hasMedium()) {
            $listings->where('medium', $search->medium);
        }

        return $listings;
    }

    /**
     * @return list<string>
     */
    private function mediaForSale(): array
    {
        /** @var list<string> $media */
        $media = array_values(Listing::query()
            ->forSale()
            ->whereNotNull('medium')
            ->distinct()
            ->orderBy('medium')
            ->pluck('medium')
            ->all());

        return $media;
    }

    private function submitted(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        return is_string($value) ? $value : null;
    }
}
