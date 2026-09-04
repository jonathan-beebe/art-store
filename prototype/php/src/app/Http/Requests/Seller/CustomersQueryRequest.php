<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Seller\ActivityKind;
use App\Domain\Seller\CustomerRow;
use App\Domain\Seller\CustomerSegment;
use App\Domain\Seller\CustomerSortColumn;
use App\Domain\Seller\SortDirection;
use App\Domain\Seller\TableSort;
use App\Models\Customer;
use App\Support\Page;
use Illuminate\Auth\Access\Response;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The customers tool's query vocabulary, shared by `GET /seller/customers`
 * (`segment`, `sort`, `dir`, `page`) and `GET /seller/customers/{customer}`
 * (`kind`). Customers are evergreen — there is no `range`; the "New this
 * period" segment reads a fixed thirty days
 * ({@see \App\Http\Controllers\Seller\CustomerController}), and a stray
 * `?range=` is a key `rules()` never names, so it validates nothing and
 * changes nothing.
 *
 * `page` answers 400 three ways a report filter's admin idiom
 * ({@see Page::of()}) does not: `0` and a non-integer fail
 * `rules()`, and {@see self::page()} refuses a page past the end rather
 * than clamping to the last one — the table has no "showing the last
 * page instead" fallback the way a filtered report does.
 */
final class CustomersQueryRequest extends SellerQueryRequest
{
    /** DECISIONS.md decision 4: fifty rows a page. */
    public const int PAGE_SIZE = 50;

    /**
     * The index route binds no customer; the show route's own customer
     * answers the ownership question `CustomerPolicy` states.
     */
    public function authorize(): Response
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer ? Gate::inspect('view', $customer) : Response::allow();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'segment' => ['nullable', Rule::enum(CustomerSegment::class)],
            'sort' => ['nullable', Rule::enum(CustomerSortColumn::class)],
            'dir' => ['nullable', Rule::enum(SortDirection::class)],
            'kind' => ['nullable', Rule::enum(ActivityKind::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Named for the buyers it narrows to: `segment()` alone is
     * {@see \Illuminate\Http\Request::segment()}, a path segment.
     */
    public function customerSegment(): CustomerSegment
    {
        return $this->enum('segment', CustomerSegment::class) ?? CustomerSegment::default();
    }

    /**
     * Any `sort` or `dir` in the query sets the sort; neither present keeps the default.
     *
     * @return TableSort<CustomerRow>
     */
    public function sort(): TableSort
    {
        $column = $this->enum('sort', CustomerSortColumn::class);
        $direction = $this->enum('dir', SortDirection::class);
        $default = CustomerSortColumn::defaultSort();

        return TableSort::of($column ?? $default->column, $direction ?? $default->direction);
    }

    /** The timeline's filter. Absent is the whole feed. */
    public function kind(): ?ActivityKind
    {
        return $this->enum('kind', ActivityKind::class);
    }

    /**
     * The page of {@see PAGE_SIZE} the seller asked for, off `$totalCount`
     * matching buyers. `rules()` already refused `0` and a non-integer;
     * a page past the end is this method's own refusal — unlike
     * `Page::of()`'s own clamp, the table has nothing sane to fall back
     * to when the page asked for holds no rows.
     */
    public function page(int $totalCount): Page
    {
        $requested = $this->integer('page', 1);
        $page = Page::of($this->stringOrNull('page'), self::PAGE_SIZE, $totalCount);

        if ($page->number !== $requested) {
            throw new HttpResponseException(response('', 400));
        }

        return $page;
    }

    /**
     * The submitted filters, in the shape every segment and sort link
     * round-trips.
     *
     * @return array<string, string>
     */
    public function roundTripped(): array
    {
        return $this->roundTrippedOf(['segment', 'sort', 'dir']);
    }
}
