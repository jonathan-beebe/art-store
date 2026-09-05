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
 * The customers tool's query vocabulary: `segment`, `sort`, `dir`, and
 * `page` for `GET /seller/customers`; `kind` for
 * `GET /seller/customers/{customer}`. `rules()` validates the whole set
 * for both routes, and each route reads what it needs. Customers are
 * evergreen — there is no `range`; the "New this period" segment reads a
 * fixed thirty days ({@see \App\Http\Controllers\Seller\CustomerController}),
 * and a stray `?range=` is a key `rules()` never names, so it validates
 * nothing and changes nothing.
 *
 * `0`, a non-integer `page`, and a page past the end all answer 400:
 * the first two fail `rules()`, and {@see self::page()} refuses the
 * third itself.
 */
final class CustomersQueryRequest extends SellerQueryRequest
{
    /** Fifty rows a page. */
    private const int PAGE_SIZE = 50;

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

    /** The column the table sorts by. */
    public function sortColumn(): CustomerSortColumn
    {
        return $this->enum('sort', CustomerSortColumn::class) ?? CustomerSortColumn::default();
    }

    /** The direction the table sorts in. */
    public function sortDirection(): SortDirection
    {
        return $this->enum('dir', SortDirection::class) ?? SortDirection::Desc;
    }

    /**
     * The column and direction together, for the chrome's column headers.
     *
     * @return TableSort<CustomerRow>
     */
    public function sort(): TableSort
    {
        return TableSort::of($this->sortColumn(), $this->sortDirection());
    }

    /** The timeline's filter. Absent is the whole feed. */
    public function kind(): ?ActivityKind
    {
        return $this->enum('kind', ActivityKind::class);
    }

    /**
     * The page of fifty the seller asked for, off `$totalCount` matching
     * buyers. A page past the end refuses. `Page::of()`'s own admin
     * callers clamp to the last page instead: this table has nothing sane
     * to fall back to when the page asked for holds no rows.
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
