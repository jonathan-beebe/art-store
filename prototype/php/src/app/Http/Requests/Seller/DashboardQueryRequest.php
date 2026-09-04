<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Analytics\AnalyticsRange;
use Illuminate\Validation\Rule;

/**
 * The dashboard's query vocabulary: `range`, the window every tile, total,
 * and strip on the page is read over.
 */
final class DashboardQueryRequest extends SellerQueryRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'range' => ['nullable', Rule::in(array_map(strval(...), AnalyticsRange::SIZES))],
        ];
    }
}
