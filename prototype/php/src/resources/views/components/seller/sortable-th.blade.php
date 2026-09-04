{{--
    One sortable column header: the column's name as a link that sorts by
    it, `aria-sort` on the cell, and a chevron on the sorted column
    pointing the way it runs. Takes an `App\Seller\ColumnHeader`.
--}}
@props(['header'])

<th scope="col" @class(['px-4 py-2 font-semibold', 'text-right' => $header->column->alignsRight()]) aria-sort="{{ $header->ariaSort }}">
    <a href="{{ $header->href }}" class="inline-flex items-center gap-1 rounded hover:text-gray-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:hover:text-gray-200">
        {{ $header->column->label() }}
        @if ($header->ariaSort !== 'none')
            <svg viewBox="0 0 16 16" fill="currentColor" width="12" height="12" aria-hidden="true" class="{{ $header->ariaSort === 'ascending' ? 'rotate-180' : '' }}"><path fill-rule="evenodd" d="M4.22 6.22a.75.75 0 0 1 1.06 0L8 8.94l2.72-2.72a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0L4.22 7.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
        @endif
    </a>
</th>
