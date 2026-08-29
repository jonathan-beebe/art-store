<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

use App\Domain\Configurator\ModifierKind;
use App\Models\Listing;
use App\Models\Variant;
use App\Support\Configurator\ConfiguratorInput;
use App\Support\Configurator\ConfiguratorPageResolver;
use App\Support\Configurator\ListingConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * The add-to-cart POST, for a legacy zero-axis listing's one-click button and
 * a configured listing's full submission alike — the axis, unit, and modifier
 * fields simply carry no data for the legacy path, so
 * {@see ConfiguratorPageResolver} resolves it to the same "nothing to
 * configure" shape either way. Required answers are enforced here, at
 * submit — not at page render, where an unanswered modifier just shows no
 * add-on yet.
 */
final class AddToCartRequest extends FormRequest
{
    private ?ListingConfiguration $configuration = null;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = ['quantity' => ['nullable', 'integer', 'min:1']];
        $configuration = $this->configuration();

        foreach ($configuration->modifiers as $modifier) {
            if (! $modifier['required']) {
                continue;
            }

            $key = "modifier.{$modifier['id']}";

            $rules[$key] = match ($modifier['kind']) {
                ModifierKind::Select => ['required', 'string'],
                ModifierKind::Text => ['required', 'string', 'max:'.($modifier['charLimit'] ?? 1024)],
                ModifierKind::Measurement => ['required', 'numeric', 'min:'.($modifier['minValue'] ?? 0)],
            };
        }

        if ($configuration->isSerialized) {
            $rules['unit'] = [
                'required',
                'string',
                Rule::exists('units', 'id')->where('variant_id', $configuration->variantId)->where('state', 'available'),
            ];
        }

        return $rules;
    }

    /**
     * The listing page posts an "Add to cart" button with no quantity field,
     * which means one.
     */
    public function quantity(): int
    {
        return $this->filled('quantity') ? $this->integer('quantity') : 1;
    }

    public function configuration(): ListingConfiguration
    {
        return $this->configuration ??= ConfiguratorPageResolver::resolve($this->listing(), ConfiguratorInput::fromRaw(
            $this->input('axis', []),
            $this->input('unit'),
            $this->input('modifier', []),
            (string) $this->quantity(),
        ));
    }

    public function variant(): ?Variant
    {
        $variantId = $this->configuration()->variantId;

        return $variantId === null ? null : Variant::find($variantId);
    }

    public function listing(): Listing
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            ? $listing
            : throw new RuntimeException('The cart route binds a listing.');
    }
}
