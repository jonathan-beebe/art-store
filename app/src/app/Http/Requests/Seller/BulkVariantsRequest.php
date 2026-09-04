<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\Listing;
use App\Models\OptionValue;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * The variant grid's bulk action: every variant selecting one axis value
 * enabled or disabled together.
 */
final class BulkVariantsRequest extends FormRequest
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
            'option_value_id' => [
                'required',
                'string',
                Rule::exists('option_values', 'id')->where(
                    fn (Builder $query): Builder => $query->whereIn('axis_id', $this->listing()->optionAxes()->pluck('id'))
                ),
            ],
            'enabled' => ['required', 'boolean'],
        ];
    }

    public function optionValue(): OptionValue
    {
        return OptionValue::findOrFail($this->string('option_value_id')->toString());
    }

    public function enabled(): bool
    {
        return $this->boolean('enabled');
    }

    public function listing(): Listing
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            ? $listing
            : throw new RuntimeException('The variant route binds a listing.');
    }
}
