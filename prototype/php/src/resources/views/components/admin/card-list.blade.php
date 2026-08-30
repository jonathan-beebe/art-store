{{-- Below `sm`, every admin table's row-per-record becomes a card in this
     list instead — same data, nothing to scroll sideways past. The
     `<table>` a card-list sits beside stays `hidden sm:table`/`sm:block` so
     at `sm` and up the table takes over and this renders nothing. --}}
@props(['caption' => null])

<div {{ $attributes->merge(['class' => 'mt-2 sm:hidden divide-y divide-gray-200 dark:divide-gray-800 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900']) }}>
    @if ($caption)
        <span class="sr-only">{{ $caption }}</span>
    @endif
    {{ $slot }}
</div>
