<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Configurator\CreateModifier;
use App\Actions\Configurator\DeleteModifier;
use App\Actions\Configurator\UpdateModifier;
use App\Configurator\ModifierIndexPageData;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Seller\ModifierRequest;
use App\Models\Listing;
use App\Models\Modifier;
use App\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class ModifierController extends SellerController
{
    public function index(Request $request, Listing $listing): View
    {
        $this->authorize('view', $listing);

        return view('seller.listings.modifiers.index', $this->indexData($request, $listing));
    }

    public function store(ModifierRequest $request, Listing $listing, CreateModifier $create, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.modifiers.index', $this->indexData($request, $listing));
        }

        $create(
            $listing,
            $request->kind(),
            $request->prompt(),
            $request->instructions(),
            $request->isRequired(),
            $request->position(),
            $request->addOnPriceCents(),
            $request->charLimit(),
            $request->unit(),
            $request->minValue(),
            $request->maxValue(),
            $request->rateCentsPerUnit(),
        );

        return redirect()->route('seller.listings.modifiers.index', $listing)->with('status', 'Question added.');
    }

    public function update(ModifierRequest $request, Listing $listing, Modifier $modifier, UpdateModifier $update, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.modifiers.index', $this->indexData($request, $listing));
        }

        $update(
            $modifier,
            $request->kind(),
            $request->prompt(),
            $request->instructions(),
            $request->isRequired(),
            $request->position(),
            $request->addOnPriceCents(),
            $request->charLimit(),
            $request->unit(),
            $request->minValue(),
            $request->maxValue(),
            $request->rateCentsPerUnit(),
        );

        return redirect()->route('seller.listings.modifiers.index', $listing)->with('status', 'Question updated.');
    }

    public function destroy(Listing $listing, Modifier $modifier, DeleteModifier $delete, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        $this->authorize('update', $listing);

        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.modifiers.index', ModifierIndexPageData::build($listing));
        }

        $delete($modifier);

        return redirect()->route('seller.listings.modifiers.index', $listing)->with('status', 'Question removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function indexData(Request $request, Listing $listing): array
    {
        $kind = $request->query('kind');

        return ModifierIndexPageData::build($listing, is_string($kind) ? $kind : null);
    }
}
