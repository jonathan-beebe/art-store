<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Seller\ActivityKind;
use App\Domain\Seller\CustomerRow;
use App\Domain\Seller\CustomerSegment;
use App\Domain\Seller\CustomerSortColumn;
use App\Domain\Seller\SortDirection;
use App\Domain\Seller\TableSort;
use App\Models\Customer;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The customers tool's query vocabulary, shared by `GET /seller/customers`
 * (`range`, `segment`, `sort`, `dir`) and `GET /seller/customers/{customer}`
 * (`kind`).
 */
final class CustomersQueryRequest extends SellerQueryRequest
{
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
            'range' => ['nullable', Rule::in(array_map(strval(...), AnalyticsRange::SIZES))],
            'segment' => ['nullable', Rule::enum(CustomerSegment::class)],
            'sort' => ['nullable', Rule::enum(CustomerSortColumn::class)],
            'dir' => ['nullable', Rule::enum(SortDirection::class)],
            'kind' => ['nullable', Rule::enum(ActivityKind::class)],
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

        return TableSort::of($column ?? CustomerSortColumn::Spent, $direction ?? SortDirection::Desc);
    }

    /** The timeline's filter. Absent is the whole feed. */
    public function kind(): ?ActivityKind
    {
        return $this->enum('kind', ActivityKind::class);
    }

    /**
     * The submitted filters, in the shape every segment and sort link
     * round-trips.
     *
     * @return array<string, string>
     */
    public function roundTripped(): array
    {
        return $this->roundTrippedOf(['range', 'segment', 'sort', 'dir']);
    }
}
