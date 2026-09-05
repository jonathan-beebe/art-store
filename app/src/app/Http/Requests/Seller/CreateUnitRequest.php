<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Money\Money;
use App\Models\Listing;
use App\Models\Variant;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * `specs` arrives as a list of labeled measurement rows (`specs[0][label]`,
 * `specs[0][value]`, …): each row pairs a label with a value because what a
 * unit measures differs by listing (height for a candlestick, weight for a
 * vintage lot) — the same reason `units.specs_json` itself is schemaless.
 * Blank rows are how a seller leaves a measurement unused, so they drop
 * out and never become empty spec entries.
 */
final class CreateUnitRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->listing());
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255', Rule::unique('units')->where('variant_id', $this->variant()->id)],
            'condition_note' => ['nullable', 'string'],
            'specs' => ['nullable', 'array'],
            'specs.*.label' => ['nullable', 'string', 'max:255'],
            'specs.*.value' => ['nullable', 'string', 'max:255'],
            'price_override' => ['nullable', 'regex:/^-?\d+(\.\d{1,2})?$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.unique' => 'This combination already has a piece with that name or number.',
            'price_override.regex' => 'The price is an amount in dollars, like 249.00 or -5.00.',
        ];
    }

    public function label(): string
    {
        return $this->string('label')->toString();
    }

    public function conditionNote(): ?string
    {
        return $this->filled('condition_note') ? $this->string('condition_note')->toString() : null;
    }

    /**
     * Folds the labeled rows into the assoc array `AddUnit`/`UpdateUnit`
     * store as `specs_json`. A row missing either half of the pair
     * contributes nothing. A set with no complete row at all comes back
     * `null`, never an empty array.
     *
     * @return array<string, string>|null
     */
    public function specs(): ?array
    {
        $specs = [];

        foreach ($this->array('specs') as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = is_string($row['label'] ?? null) ? trim($row['label']) : '';
            $value = is_string($row['value'] ?? null) ? trim($row['value']) : '';

            if ($label === '' || $value === '') {
                continue;
            }

            $specs[$label] = $value;
        }

        return $specs === [] ? null : $specs;
    }

    public function priceOverrideCents(): ?int
    {
        return $this->filled('price_override') ? Money::fromDollars($this->string('price_override')->toString())->cents : null;
    }

    public function listing(): Listing
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            ? $listing
            : throw new RuntimeException('The unit route binds a listing.');
    }

    public function variant(): Variant
    {
        $variant = $this->route('variant');

        return $variant instanceof Variant
            ? $variant
            : throw new RuntimeException('The unit route binds a variant.');
    }
}
