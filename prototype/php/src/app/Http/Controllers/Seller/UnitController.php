<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Configurator\AddUnit;
use App\Actions\Configurator\UpdateUnit;
use App\Domain\Configurator\UnitLabelOrder;
use App\Domain\Configurator\UnitState;
use App\Domain\Money\Money;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Seller\CreateUnitRequest;
use App\Http\Requests\Seller\UpdateUnitRequest;
use App\Models\Listing;
use App\Models\Unit;
use App\Models\Variant;
use App\Models\VariantOption;
use App\Support\Configurator\ConfiguratorInput;
use App\Support\Configurator\UnitSpecLines;
use App\Support\Configurator\UnitSpecRows;
use App\Support\Configurator\UnitStateCounts;
use App\Support\Configurator\UnitStateWord;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class UnitController extends SellerController
{
    public function index(Request $request, Listing $listing, Variant $variant): View
    {
        $this->authorize('view', $listing);

        $edit = $request->query('edit');

        return view('seller.listings.variants.units.index', $this->indexData($listing, $variant, is_string($edit) ? $edit : null, $request));
    }

    public function store(CreateUnitRequest $request, Listing $listing, Variant $variant, AddUnit $add, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.variants.units.index', $this->indexData($listing, $variant));
        }

        $add($variant, $request->label(), $request->conditionNote(), $request->specs(), $request->priceOverrideCents());

        return redirect()->route('seller.listings.variants.units.index', [$listing, $variant])->with('status', 'Piece added.');
    }

    public function update(
        UpdateUnitRequest $request,
        Listing $listing,
        Variant $variant,
        Unit $unit,
        UpdateUnit $update,
        RateLimitGate $rateLimit,
    ): RedirectResponse|Response {
        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.variants.units.index', $this->indexData($listing, $variant));
        }

        $update($unit, $request->label(), $request->state(), $request->conditionNote(), $request->specs(), $request->priceOverrideCents());

        return redirect()->route('seller.listings.variants.units.index', [$listing, $variant])->with('status', 'Piece updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function indexData(Listing $listing, Variant $variant, ?string $editingUnitId = null, ?Request $request = null): array
    {
        $units = $variant->units()->get()->sort(fn (Unit $a, Unit $b): int => UnitLabelOrder::compare($a->label, $b->label))->values();
        $combinationPrice = $variant->resolvedPrice($listing->price());

        return [
            'listing' => $listing,
            'variant' => $variant,
            'comboLabel' => self::comboLabel($variant),
            'counts' => UnitStateCounts::tally($units),
            'pieces' => $units->map(fn (Unit $unit): array => self::piece($unit, $combinationPrice)),
            'editingUnitId' => $editingUnitId,
            // Opens on the exact combination this screen manages rather than
            // the listing's own defaults; a live change in the panel then
            // round-trips on this URL and overrides it (IMPRV-015).
            'buyerViewInput' => ConfiguratorInput::fromQuery($request ?? request(), self::axisSelections($variant), null, 1),
        ];
    }

    /**
     * @return array{id: string, unit: Unit, stateWord: string, isAvailable: bool, isSold: bool, specLines: list<string>, specRows: list<array{label: string, value: string}>, price: Money}
     */
    private static function piece(Unit $unit, Money $combinationPrice): array
    {
        return [
            'id' => $unit->id,
            'unit' => $unit,
            'stateWord' => UnitStateWord::forState($unit->state),
            'isAvailable' => $unit->state === UnitState::Available,
            'isSold' => $unit->state === UnitState::Sold,
            'specLines' => UnitSpecLines::format($unit->specs_json),
            'specRows' => UnitSpecRows::forEditing($unit->specs_json),
            'price' => $unit->price_override_cents === null ? $combinationPrice : Money::fromCents($unit->price_override_cents),
        ];
    }

    /**
     * The option labels naming this combination, for a page that manages one
     * variant's pieces among possibly several — null when the listing has no
     * axes at all, since then no combination needs naming.
     */
    private static function comboLabel(Variant $variant): ?string
    {
        $labels = $variant->options()->with('optionValue')->get()
            ->map(fn (VariantOption $option): ?string => $option->optionValue?->label)
            ->filter()
            ->values();

        return $labels->isEmpty() ? null : $labels->implode(' / ');
    }

    /**
     * The axis selections that pick this exact combination, so the buyer-view
     * panel resolves to the same pieces this screen manages rather than the
     * listing's default choices.
     *
     * @return array<string, string>
     */
    private static function axisSelections(Variant $variant): array
    {
        /** @var array<string, string> $selections */
        $selections = $variant->options()->pluck('option_value_id', 'axis_id')->all();

        return $selections;
    }
}
