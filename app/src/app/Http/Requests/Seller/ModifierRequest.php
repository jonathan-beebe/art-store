<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Configurator\PriceDifferenceInput;
use App\Domain\Configurator\ModifierKind;
use App\Models\Listing;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * One form serves every {@see ModifierKind} — a text prompt, a select with
 * its own options screen, or a measurement with a rate. Only the pricing
 * fields a kind actually reads differ, and each is nullable either way.
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
            'add_on_price' => ['nullable', 'string', self::money('The extra charge is an amount in dollars, like 2.00.')],
            'char_limit' => ['nullable', 'integer', 'min:1'],
            'unit' => ['nullable', 'string', 'max:255'],
            'min_value' => ['nullable', 'numeric'],
            'max_value' => ['nullable', 'numeric'],
            'rate' => ['nullable', 'string', self::money('The rate is an amount in dollars, like 0.50.')],
        ];
    }

    /**
     * A validation rule closure reused for both money fields — a value is
     * valid whenever {@see PriceDifferenceInput} can read it, so "2.00",
     * "+$2.00", and "—" all pass.
     */
    private static function money(string $failureMessage): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($failureMessage): void {
            if (! PriceDifferenceInput::isValid(is_string($value) ? $value : null)) {
                $fail($failureMessage);
            }
        };
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
        return $this->filled('add_on_price') ? PriceDifferenceInput::parseCents($this->string('add_on_price')->toString()) : 0;
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
        return $this->filled('rate') ? PriceDifferenceInput::parseCents($this->string('rate')->toString()) : null;
    }

    public function listing(): Listing
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            ? $listing
            : throw new RuntimeException('The modifier route binds a listing.');
    }
}
