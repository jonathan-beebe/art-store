<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Configurator\ConfiguratorPublishValidation;
use App\Models\Listing;
use App\Models\QuantityBreak;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * The ≤10-tier cap is enforced here, on a new tier only, so the seller sees
 * the refusal before typing an 11th row rather than only at publish —
 * the same cap `ConfiguratorPublishValidation::MAX_QUANTITY_TIERS` judges,
 * read from there rather than repeated as a second magic number.
 */
final class QuantityBreakRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->listing());
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'min_qty' => ['required', 'integer', 'min:2'],
            'discount_bps' => ['required', 'integer', 'min:1', 'max:9999'],
        ];
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
        return $this->integer('discount_bps');
    }

    public function listing(): Listing
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            ? $listing
            : throw new RuntimeException('The quantity break route binds a listing.');
    }
}
