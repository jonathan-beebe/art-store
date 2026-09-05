<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Configurator\ConfiguratorPublishValidation;
use App\Models\Listing;
use App\Models\QuantityBreak;
use App\Support\Configurator\QuantityBreakPercent;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * The ≤10-tier cap is enforced here, on a new tier only, so the seller sees
 * the refusal while typing the row, ahead of publish. This reads the same
 * cap `ConfiguratorPublishValidation::MAX_QUANTITY_TIERS` judges, so no
 * second magic number exists. The seller types a percent;
 * {@see QuantityBreakPercent} converts it to the `discount_bps` the frozen
 * add/update actions store.
 */
final class QuantityBreakRequest extends FormRequest
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
            'min_qty' => ['required', 'integer', 'min:2'],
            'discount_percent' => ['required', 'string', self::percent()],
        ];
    }

    /**
     * A discount is valid whenever {@see QuantityBreakPercent} can read it —
     * "10", "12.5", and "0.01" all pass; a third decimal place, zero, or
     * anything over 99.99 does not.
     */
    private static function percent(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! QuantityBreakPercent::isValid($value)) {
                $fail('The discount is a percent between 0.01 and 99.99, like 12.5.');
            }
        };
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $isNewTier = ! $this->route('quantity_break') instanceof QuantityBreak;

            if ($isNewTier && $this->listing()->quantityBreaks()->count() >= ConfiguratorPublishValidation::MAX_QUANTITY_TIERS) {
                $validator->errors()->add(
                    'min_qty',
                    'This listing already holds '.ConfiguratorPublishValidation::MAX_QUANTITY_TIERS.' quantity tiers, the most allowed.',
                );
            }
        });
    }

    public function minQty(): int
    {
        return $this->integer('min_qty');
    }

    public function discountBps(): int
    {
        return QuantityBreakPercent::toBps($this->string('discount_percent')->toString());
    }

    public function listing(): Listing
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            ? $listing
            : throw new RuntimeException('The quantity break route binds a listing.');
    }
}
