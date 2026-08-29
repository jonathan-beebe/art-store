<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Configurator\PricingMode;
use App\Models\Listing;
use App\Models\OptionAxis;
use App\Models\Property;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use RuntimeException;

final class OptionAxisRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'integer', 'min:0'],
            'property_id' => ['nullable', 'string', Rule::exists('properties', 'id')],
            'pricing_mode' => ['sometimes', 'string', Rule::enum(PricingMode::class)],
        ];
    }

    public function name(): string
    {
        return $this->string('name')->toString();
    }

    public function position(): int
    {
        return $this->integer('position');
    }

    public function property(): ?Property
    {
        return $this->filled('property_id') ? Property::find($this->string('property_id')->toString()) : null;
    }

    /**
     * The choice's pricing mode this request asks for — an explicit
     * `pricing_mode` field when one is sent, else the axis being updated
     * keeps the mode it already has, or a brand-new axis defaults to
     * `add_on`. This screen has no mode picker yet (phase B), so every form
     * on it today posts with the field absent and gets exactly today's
     * behavior either way.
     */
    public function pricingMode(): PricingMode
    {
        if ($this->filled('pricing_mode')) {
            return PricingMode::from($this->string('pricing_mode')->toString());
        }

        $axis = $this->route('option_axis');

        return $axis instanceof OptionAxis ? $axis->pricing_mode : PricingMode::AddOn;
    }

    public function listing(): Listing
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            ? $listing
            : throw new RuntimeException('The option axis route binds a listing.');
    }
}
