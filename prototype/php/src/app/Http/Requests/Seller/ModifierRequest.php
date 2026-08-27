<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Configurator\ModifierKind;
use App\Domain\Money\Money;
use App\Models\Listing;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * One form for every {@see ModifierKind} — a text prompt, a select with its
 * own options screen, or a measurement with a rate — rather than three, since
 * only the pricing fields a kind actually reads differ, and each is nullable
 * either way.
 */
final class ModifierRequest extends FormRequest
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
            'kind' => ['required', Rule::enum(ModifierKind::class)],
            'prompt' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
            'required' => ['nullable', 'boolean'],
            'position' => ['required', 'integer', 'min:0'],
            'add_on_price' => ['nullable', 'regex:/^-?\d+(\.\d{1,2})?$/'],
            'char_limit' => ['nullable', 'integer', 'min:1'],
            'unit' => ['nullable', 'string', 'max:255'],
            'min_value' => ['nullable', 'numeric'],
            'max_value' => ['nullable', 'numeric'],
            'rate' => ['nullable', 'regex:/^-?\d+(\.\d{1,2})?$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'add_on_price.regex' => 'The add-on price is an amount in dollars, like 5.00.',
            'rate.regex' => 'The rate is an amount in dollars, like 0.50.',
        ];
    }

    public function kind(): ModifierKind
    {
        return $this->enum('kind', ModifierKind::class) ?? throw new RuntimeException('The kind rule admits only modifier kinds.');
    }

    public function prompt(): string
    {
        return $this->string('prompt')->toString();
    }

    public function instructions(): ?string
    {
        return $this->filled('instructions') ? $this->string('instructions')->toString() : null;
    }

    public function isRequired(): bool
    {
        return $this->boolean('required');
    }

    public function position(): int
    {
        return $this->integer('position');
    }

    public function addOnPriceCents(): int
    {
        return $this->filled('add_on_price') ? Money::fromDollars($this->string('add_on_price')->toString())->cents : 0;
    }

    public function charLimit(): ?int
    {
        return $this->filled('char_limit') ? $this->integer('char_limit') : null;
    }

    public function unit(): ?string
    {
        return $this->filled('unit') ? $this->string('unit')->toString() : null;
    }

    public function minValue(): ?float
    {
        return $this->filled('min_value') ? (float) $this->string('min_value')->toString() : null;
    }

    public function maxValue(): ?float
    {
        return $this->filled('max_value') ? (float) $this->string('max_value')->toString() : null;
    }

    public function rateCentsPerUnit(): ?int
    {
        return $this->filled('rate') ? Money::fromDollars($this->string('rate')->toString())->cents : null;
    }

    public function listing(): Listing
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            ? $listing
            : throw new RuntimeException('The modifier route binds a listing.');
    }
}
