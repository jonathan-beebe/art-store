<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Configurator\SetModifierScope;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Seller\ModifierScopeRequest;
use App\Models\Listing;
use App\Models\Modifier;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class ModifierScopeController extends SellerController
{
    public function __invoke(ModifierScopeRequest $request, Listing $listing, Modifier $modifier, SetModifierScope $setScope, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.modifiers.index', [
                'listing' => $listing,
                'modifiers' => $listing->modifiers()->with(['options', 'scopes'])->orderBy('position')->get(),
                'axes' => $listing->optionAxes()->with('optionValues')->orderBy('position')->get(),
            ]);
        }

        $setScope($modifier, $request->optionValues());

        return redirect()->route('seller.listings.modifiers.index', $listing)->with('status', 'Scope updated.');
    }
}
