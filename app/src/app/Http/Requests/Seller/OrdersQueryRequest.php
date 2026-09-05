<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Fulfillment\LaneFilter;
use App\Domain\Seller\ActivityKind;
use Illuminate\Validation\Rule;

/**
 * The orders tool's query vocabulary, shared by `GET /seller/orders`
 * (`lane`) and `GET /seller/orders/{fulfillment}` (`lane`, `kind`). An
 * absent `kind` is the whole feed, which is what the filter's All link
 * leaves behind.
 */
final class OrdersQueryRequest extends SellerQueryRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'lane' => ['nullable', Rule::enum(LaneFilter::class)],
            'kind' => ['nullable', Rule::enum(ActivityKind::class)],
        ];
    }

    /**
     * The tab the list pane opens on: the query's, else the one the page
     * already stands on — the default lane on the index, and the lane the
     * open parcel sits in on a detail reached by a link that named none.
     */
    public function lane(LaneFilter $fallback): LaneFilter
    {
        return $this->enum('lane', LaneFilter::class) ?? $fallback;
    }

    /** The feed's filter; null is the whole feed. */
    public function kind(): ?ActivityKind
    {
        return $this->enum('kind', ActivityKind::class);
    }
}
