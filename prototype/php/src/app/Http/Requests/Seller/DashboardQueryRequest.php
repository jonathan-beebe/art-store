<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Analytics\AnalyticsRange;
use Illuminate\Validation\Rule;

/**
 * The dashboard's query vocabulary: `range`, the window every tile, total,
 * and strip on the page is read over. The dashboard is the only seller
 * page that owns `range` — listings and customers are evergreen and read
 * a fixed window of their own.
 */
final class DashboardQueryRequest extends SellerQueryRequest
{
    private const int DEFAULT_RANGE_DAYS = 30;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'range' => ['nullable', Rule::in(array_map(strval(...), AnalyticsRange::SIZES))],
        ];
    }

    /** The `range` the dashboard is read over, defaulted when absent. */
    public function rangeDays(): int
    {
        $value = $this->stringOrNull('range');

        return $value === null ? self::DEFAULT_RANGE_DAYS : (int) $value;
    }
}
