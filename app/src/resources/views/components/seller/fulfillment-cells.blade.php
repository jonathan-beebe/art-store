{{--
    The seller orders list pane's rows: the buyer's name leads (who a
    seller recognises a sale by), the muted line is the scan line — item and
    the total the buyer paid — and the status badge and the date placed sit
    in the meta column. A row that has something to say carries one more
    line: what the buyer asked and nobody answered, else the last step the
    seller marked done. Every value is read off the {@see \App\Seller\OrderRow}
    the pane hands over.
--}}
@props(['pane'])

<div class="flex flex-col divide-y divide-gray-200 dark:divide-white/10">
    @forelse ($pane->rows as $row)
        <x-pane-row
            accent="indigo"
            :selected="$row->selected"
            href="{{ $row->href }}"
            :aria-current="$row->selected ? 'page' : null"
            data-pane-cell="{{ $row->id }}"
        >
            <x-slot:title>
                <p class="truncate text-sm/6 font-semibold text-gray-900 dark:text-white">{{ $row->buyer }}</p>
            </x-slot:title>
            <x-slot:supporting>
                <p class="mt-1 truncate text-xs/5 text-gray-500 dark:text-gray-400">
                    {{ $row->itemLabel }} · {{ $row->subtotal }}
                </p>
                @if ($row->note !== null)
                    <p class="mt-1 truncate text-xs/5 text-yellow-700 dark:text-yellow-500">{{ $row->note }}</p>
                @endif
            </x-slot:supporting>
            <x-slot:meta>
                <x-seller.status-badge :tint="$row->tint">{{ $row->statusLabel }}</x-seller.status-badge>
                <p class="mt-1 text-xs/5 text-gray-500 dark:text-gray-400">{{ $row->placed }}</p>
            </x-slot:meta>
        </x-pane-row>
    @empty
        <p class="m-3 rounded-md border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-4 text-sm text-gray-600 dark:text-gray-400">{{ $pane->lane->emptyMessage() }}</p>
    @endforelse
</div>
