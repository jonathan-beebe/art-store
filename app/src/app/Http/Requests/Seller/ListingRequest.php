<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Configurator\AbsolutePriceInput;
use App\Configurator\PriceDifferenceInput;
use App\Domain\Listings\ListingCreationChoice;
use App\Domain\Listings\ListingCreationShape;
use App\Domain\Listings\ListingDraft;
use App\Domain\Money\Money;
use App\Models\Listing;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Override;

/**
 * Two very different forms behind one request, told apart by whether the
 * route names a listing: a create names none, and asks only for the guided
 * on-ramp DSGN-003 routes it through (title, pricing shape, and that shape's
 * own fields — never description, dimensions, category, or an image, which
 * all wait on the hub); an update names one and keeps the Basics screen's
 * full field set.
 */
final class ListingRequest extends FormRequest
{
    private const MAX_IMAGE_KILOBYTES = 5120;

    /**
     * A create names no listing to own; an update names one, and it is the
     * signed-in seller's or it does not exist for them.
     */
    public function authorize(): Response
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            ? Gate::inspect('update', $listing)
            : Response::allow();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing ? $this->updateRules($listing) : $this->createRules();
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'price.regex' => 'The price is an amount in dollars, like 249.00.',
        ];
    }

    /**
     * The picker's "seller's default" option submits as an empty string.
     * `exists` reads it as a value to look up, so it is blanked to null
     * here, the value `nullable` skips the lookup for.
     */
    #[Override]
    protected function prepareForValidation(): void
    {
        if ($this->input('fulfillment_flow_id') === '') {
            $this->merge(['fulfillment_flow_id' => null]);
        }
    }

    /**
     * The create path's own cross-field checks — a version or an extra
     * option row is either complete (label and price) or wholly blank (a
     * dropped placeholder row); a shape that needs at least one complete row
     * reports one error for the whole set.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $listing = $this->route('listing');

            if ($listing instanceof Listing) {
                return;
            }

            match (ListingCreationShape::tryFrom($this->string('shape')->toString())) {
                ListingCreationShape::Versions => $this->validateVersionRows($validator),
                ListingCreationShape::Extras => $this->validateExtraRows($validator),
                default => null,
            };
        });
    }

    /**
     * A configured listing's Basics save carries no price or quantity field
     * at all — the Basics screen never renders them — so the draft keeps
     * whatever the listing already holds; there is no input to parse.
     * `listings.price_cents` may be sync-derived at that point
     * ({@see \App\Configurator\ListingPriceSync}); reading it back
     * unchanged here is what keeps a Basics save from clobbering it. A
     * create builds its draft from whichever on-ramp shape was submitted
     * instead — none of those fields exist on this path.
     */
    public function toDraft(): ListingDraft
    {
        $listing = $this->route('listing');
        $listing = $listing instanceof Listing ? $listing : null;

        if ($listing === null) {
            return $this->createDraft();
        }

        return ListingDraft::of(
            $this->string('title')->toString(),
            $this->optionalString('description'),
            $this->optionalString('dimensions'),
            $this->filled('price') ? Money::fromDollars($this->string('price')->toString()) : $listing->price(),
            $this->boolean('made_to_order') ? null : ($this->filled('quantity') ? $this->integer('quantity') : $listing->quantity),
            $this->optionalString('category_id'),
            // The picker renders only for a seller with more than one
            // workflow. A seller with one submits no field at all, and
            // `has()` keeps the listing's own value for that request; a
            // seller with the picker submits the field, blank or set, and
            // that value writes.
            $this->has('fulfillment_flow_id') ? $this->optionalString('fulfillment_flow_id') : $listing->fulfillment_flow_id,
        );
    }

    public function shape(): ListingCreationShape
    {
        return ListingCreationShape::from($this->string('shape')->toString());
    }

    /**
     * The one choice the chosen shape adds to the new listing, or null for a
     * plain listing: the versions ramp always adds one; the extras ramp adds
     * one only when the seller named it and filled a row.
     */
    public function creationChoice(): ?ListingCreationChoice
    {
        return match ($this->shape()) {
            ListingCreationShape::OneThing => null,
            ListingCreationShape::Versions => ListingCreationChoice::versions($this->choiceName(), $this->versionRows()),
            ListingCreationShape::Extras => $this->extraOptionRows() === []
                ? null
                : ListingCreationChoice::extras($this->extraChoiceName(), $this->extraOptionRows()),
        };
    }

    public function choiceName(): string
    {
        return $this->string('choice_name')->toString();
    }

    public function extraChoiceName(): string
    {
        return trim($this->string('extra_choice_name')->toString());
    }

    /**
     * The versions ramp's complete rows — a fully blank prefilled row is
     * dropped, never surfaced as an option.
     *
     * @return list<array{label: string, cents: int}>
     */
    public function versionRows(): array
    {
        return $this->completeRows('versions', AbsolutePriceInput::parseCents(...));
    }

    /**
     * The extras ramp's complete option rows — empty once the seller pressed
     * "Create with just the price", or left both the choice name and every
     * row blank (the same outcome, reached without the link).
     *
     * @return list<array{label: string, cents: int}>
     */
    public function extraOptionRows(): array
    {
        if ($this->boolean('skip_extra') || $this->extraChoiceName() === '') {
            return [];
        }

        return $this->completeRows('extra_options', PriceDifferenceInput::parseCents(...));
    }

    private function optionalString(string $key): ?string
    {
        return $this->filled($key) ? $this->string($key)->toString() : null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function updateRules(Listing $listing): array
    {
        // A listing that already offers a choice or breaks into serialized
        // pieces no longer owns its price and stock count — the Basics
        // screen doesn't render those fields for it, so nothing requires
        // them here either.
        $ownsPriceAndStock = $listing->hasOwnPriceAndStock();
        $madeToOrder = $this->boolean('made_to_order');

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'dimensions' => ['nullable', 'string', 'max:255'],
            'price' => [Rule::requiredIf($ownsPriceAndStock), 'nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'made_to_order' => ['nullable', 'boolean'],
            'quantity' => [Rule::requiredIf($ownsPriceAndStock && ! $madeToOrder), 'nullable', 'integer', 'min:0', 'max:999'],
            'category_id' => ['nullable', 'string', Rule::exists('categories', 'id')],
            'fulfillment_flow_id' => ['nullable', 'string', Rule::exists('fulfillment_flows', 'id')->where('seller_id', $listing->seller_id)],
            // `image` and `mimes` both read the declared type, which an upload
            // controls. `dimensions` decodes the file, so a text file renamed
            // .jpg is rejected here, never served as a broken listing image.
            'image' => ['nullable', 'file', 'image', 'mimes:jpeg,png,webp,gif', 'dimensions:min_width=1,min_height=1', 'max:'.self::MAX_IMAGE_KILOBYTES],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function createRules(): array
    {
        $shape = ListingCreationShape::tryFrom($this->string('shape')->toString());

        return [
            'shape' => ['required', Rule::enum(ListingCreationShape::class)],
            'title' => ['required', 'string', 'max:255'],
            ...match ($shape) {
                ListingCreationShape::Versions => $this->versionsRules(),
                ListingCreationShape::Extras => $this->extrasRules(),
                default => $this->oneThingRules(),
            },
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function oneThingRules(): array
    {
        return [
            'price' => ['required', 'string', $this->absolutePrice('The price is an amount in dollars, like 249.00.')],
            'made_to_order' => ['nullable', 'boolean'],
            'quantity' => [Rule::requiredIf(! $this->boolean('made_to_order')), 'nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function extrasRules(): array
    {
        return $this->oneThingRules() + [
            'extra_choice_name' => ['nullable', 'string', 'max:255'],
            'extra_options' => ['array'],
            'extra_options.*.label' => ['nullable', 'string', 'max:255'],
            'extra_options.*.price' => ['nullable', 'string'],
            'skip_extra' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function versionsRules(): array
    {
        return [
            'choice_name' => ['required', 'string', 'max:255'],
            'versions' => ['array'],
            'versions.*.label' => ['nullable', 'string', 'max:255'],
            'versions.*.price' => ['nullable', 'string'],
        ];
    }

    private function absolutePrice(string $message): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($message): void {
            if (! AbsolutePriceInput::isValid(is_string($value) ? $value : null)) {
                $fail($message);
            }
        };
    }

    private function validateVersionRows(Validator $validator): void
    {
        $completeCount = $this->validateRows($validator, 'versions', AbsolutePriceInput::isValid(...), 'Name this version.', 'The price is an amount in dollars, like 18.00.');

        if ($completeCount === 0) {
            $validator->errors()->add('versions', 'Add at least one version and its price.');
        }
    }

    private function validateExtraRows(Validator $validator): void
    {
        if ($this->boolean('skip_extra')) {
            return;
        }

        $choiceName = $this->extraChoiceName();
        $completeCount = $this->validateRows($validator, 'extra_options', PriceDifferenceInput::isValid(...), 'Name this option.', 'The amount it adds is a dollar figure, like +6.00 or -2.00.');

        if ($choiceName === '' && $completeCount === 0) {
            // Nothing entered on either field — the same outcome "Create
            // with just the price" reaches explicitly.
            return;
        }

        if ($choiceName === '') {
            $validator->errors()->add('extra_choice_name', 'Name the choice these options belong to.');
        }

        if ($completeCount === 0) {
            $validator->errors()->add('extra_options', 'Add at least one option and what it adds.');
        }
    }

    /**
     * Walks a `{$key}.*.label` / `{$key}.*.price` row set, flagging every
     * half-filled row (one field present, the other blank) and every filled
     * price that does not parse, and reports how many rows are complete —
     * both shared by the versions and extras row sets, which differ only in
     * which price format they accept and what an incomplete row's messages
     * say.
     *
     * @param  Closure(string): bool  $isValidPrice
     */
    private function validateRows(Validator $validator, string $key, Closure $isValidPrice, string $labelMessage, string $priceMessage): int
    {
        $rows = $this->input($key);
        $rows = is_array($rows) ? $rows : [];
        $completeCount = 0;

        foreach ($rows as $index => $row) {
            [$label, $price] = self::rowLabelAndPrice($row);

            if ($label === null && $price === null) {
                continue;
            }

            if (self::isRowComplete($validator, $key, $index, $label, $price, $isValidPrice, $labelMessage, $priceMessage)) {
                $completeCount++;
            }
        }

        return $completeCount;
    }

    /**
     * A row's label and price, each present only once it is a non-blank
     * string — the row-set fields post as strings or are absent, never as
     * another scalar type.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private static function rowLabelAndPrice(mixed $row): array
    {
        return [
            self::filledField($row, 'label'),
            self::filledField($row, 'price'),
        ];
    }

    private static function filledField(mixed $row, string $key): ?string
    {
        $value = is_array($row) ? ($row[$key] ?? null) : null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * Whether the row is complete — a present label and a price that
     * parses — after recording the label and/or price error a half-filled
     * or unparseable row carries.
     */
    private static function isRowComplete(Validator $validator, string $key, int|string $index, ?string $label, ?string $price, Closure $isValidPrice, string $labelMessage, string $priceMessage): bool
    {
        if ($label === null) {
            $validator->errors()->add("{$key}.{$index}.label", $labelMessage);
        }

        $validPrice = $price !== null && $isValidPrice($price);

        if (! $validPrice) {
            $validator->errors()->add("{$key}.{$index}.price", $priceMessage);
        }

        return $label !== null && $validPrice;
    }

    /**
     * @param  Closure(string): int  $parseCents
     * @return list<array{label: string, cents: int}>
     */
    private function completeRows(string $key, Closure $parseCents): array
    {
        $rows = $this->input($key);
        $rows = is_array($rows) ? $rows : [];
        $complete = [];

        foreach ($rows as $row) {
            $label = is_array($row) ? ($row['label'] ?? null) : null;
            $price = is_array($row) ? ($row['price'] ?? null) : null;

            if (! is_string($label) || trim($label) === '' || ! is_string($price) || trim($price) === '') {
                continue;
            }

            $complete[] = ['label' => trim($label), 'cents' => $parseCents($price)];
        }

        return $complete;
    }

    /**
     * The on-ramp's own draft: never a description, dimensions, or category
     * — all three wait on the Basics screen.
     */
    private function createDraft(): ListingDraft
    {
        if ($this->shape() === ListingCreationShape::Versions) {
            // No base price is asked on this ramp — ListingPriceSync sets
            // `price_cents` from the default version once it exists.
            return ListingDraft::of($this->string('title')->toString(), null, null, Money::zero(), null);
        }

        return ListingDraft::of(
            $this->string('title')->toString(),
            null,
            null,
            Money::fromCents(AbsolutePriceInput::parseCents($this->string('price')->toString())),
            $this->boolean('made_to_order') ? null : $this->integer('quantity'),
        );
    }
}
