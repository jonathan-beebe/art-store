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
 * "When to ask it" — Always, or Only when one of the checked boxes names one
 * of the listing's own option values. The Always radio wins outright: a
 * seller who switches back to it is done with the scope regardless of which
 * boxes still show checked underneath, the same "leave it alone" gesture as
 * never having scoped the question at all.
 */
final class ModifierScopeRequest extends FormRequest
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
            'when' => ['nullable', 'in:always,only'],
            'option_value_id' => ['array'],
            'option_value_id.*' => [
                'string',
                Rule::exists('option_values', 'id')->where(
                    fn (Builder $query): Builder => $query->whereIn('axis_id', $this->listing()->optionAxes()->pluck('id'))
                ),
            ],
        ];
    }

    /**
     * @return list<OptionValue>
     */
    public function optionValues(): array
    {
        if ($this->string('when')->toString() === 'always') {
            return [];
        }

        /** @var list<string> $ids */
        $ids = $this->input('option_value_id', []);

        return array_values(OptionValue::whereIn('id', $ids)->get()->all());
    }

    public function listing(): Listing
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            ? $listing
            : throw new RuntimeException('The modifier scope route binds a listing.');
    }
}
