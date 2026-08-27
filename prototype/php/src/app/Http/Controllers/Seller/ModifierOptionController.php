<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Configurator\AddModifierOption;
use App\Actions\Configurator\DeleteModifierOption;
use App\Actions\Configurator\UpdateModifierOption;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Seller\ModifierOptionRequest;
use App\Models\Listing;
use App\Models\Modifier;
use App\Models\ModifierOption;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class ModifierOptionController extends SellerController
{
    public function store(
        ModifierOptionRequest $request,
        Listing $listing,
        Modifier $modifier,
        AddModifierOption $add,
        RateLimitGate $rateLimit,
    ): RedirectResponse|Response {
        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.modifiers.index', $this->indexData($listing));
        }

        $add($modifier, $request->label(), $request->addOnPriceCents(), $request->position());

        return redirect()->route('seller.listings.modifiers.index', $listing)->with('status', 'Modifier option added.');
    }

    public function update(
        ModifierOptionRequest $request,
        Listing $listing,
        Modifier $modifier,
        ModifierOption $option,
        UpdateModifierOption $update,
        RateLimitGate $rateLimit,
    ): RedirectResponse|Response {
        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.modifiers.index', $this->indexData($listing));
        }

        $update($option, $request->label(), $request->addOnPriceCents(), $request->position());

        return redirect()->route('seller.listings.modifiers.index', $listing)->with('status', 'Modifier option updated.');
    }

    public function destroy(
        Listing $listing,
        Modifier $modifier,
        ModifierOption $option,
        DeleteModifierOption $delete,
        RateLimitGate $rateLimit,
    ): RedirectResponse|Response {
        $this->authorize('update', $listing);

        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.modifiers.index', $this->indexData($listing));
        }

        $delete($option);

        return redirect()->route('seller.listings.modifiers.index', $listing)->with('status', 'Modifier option removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function indexData(Listing $listing): array
    {
        return [
            'listing' => $listing,
            'modifiers' => $listing->modifiers()->with(['options', 'scopes'])->orderBy('position')->get(),
            'axes' => $listing->optionAxes()->with('optionValues')->orderBy('position')->get(),
        ];
    }
}
