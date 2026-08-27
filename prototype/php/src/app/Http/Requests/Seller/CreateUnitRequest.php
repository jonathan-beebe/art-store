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
 * `specs` is typed as raw JSON rather than a set of named fields, because
 * what a unit measures differs by listing (height for a candlestick, weight
 * for a vintage lot) — the same reason `units.specs_json` itself is schemaless.
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
            'specs' => ['nullable', 'json'],
            'price_override' => ['nullable', 'regex:/^-?\d+(\.\d{1,2})?$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.unique' => 'This variant already has a unit with that label.',
            'specs.json' => 'Specs must be valid JSON, like {"height_mm": 240}.',
            'price_override.regex' => 'The price override is an amount in dollars, like 249.00 or -5.00.',
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
     * @return array<string, int|float|string|bool>|null
     */
    public function specs(): ?array
    {
        if (! $this->filled('specs')) {
            return null;
        }

        $decoded = json_decode($this->string('specs')->toString(), true);

        if (! is_array($decoded)) {
            return null;
        }

        /** @var array<string, int|float|string|bool> $specs */
        $specs = $decoded;

        return $specs;
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
