<x-layouts.admin title="Ledger — Art Store admin">
    <h1 class="text-xl font-semibold">Ledger</h1>

    <x-admin.filters :action="route('admin.ledger')">
        <x-admin.seller-filter :sellers="$sellers" :selected="$selectedSeller" />
        <x-admin.type-filter :cases="$entryTypes" :selected="$selectedType" />
    </x-admin.filters>

    {{-- Headline figures: the shared-borders stat-tile grid — one hairline
         gap between cells rather than a border per card. --}}
    <div class="mt-4 grid grid-cols-2 gap-px overflow-hidden rounded-lg bg-stone-200 ring-1 ring-stone-200 sm:grid-cols-4 dark:bg-white/10 dark:ring-white/10">
        <x-stat-tile accent="stone" label="Held" data-stat="held">{{ $totals->held->format() }}</x-stat-tile>
        <x-stat-tile accent="stone" label="Available" data-stat="available">{{ $totals->available->format() }}</x-stat-tile>
        <x-stat-tile accent="stone" label="Paid out" data-stat="paid-out">{{ $totals->paidOut->format() }}</x-stat-tile>
        <x-stat-tile accent="stone" label="Refunded" data-stat="refunded">{{ $totals->refunded->format() }}</x-stat-tile>
    </div>

    @if ($entries->isEmpty())
        <x-admin.nothing class="mt-4">No ledger entries match this filter.</x-admin.nothing>
    @else
        <div class="mt-4 hidden overflow-x-auto rounded border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 sm:block">
            <table class="w-full text-left">
                <caption class="sr-only">Every ledger entry matching the filter above</caption>
                <thead class="border-b border-stone-300 dark:border-stone-700 bg-stone-50 dark:bg-stone-800/50">
                    <tr>
                        <th scope="col" class="px-4 py-2 font-semibold">When</th>
                        <th scope="col" class="px-4 py-2 font-semibold">Seller</th>
                        <th scope="col" class="px-4 py-2 font-semibold">Type</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
                    @foreach ($entries as $entry)
                        <tr data-entry="{{ $entry->id }}">
                            <td class="px-4 py-2 text-stone-500 dark:text-stone-400">{{ $entry->occurred_at?->format('M j, Y g:i A') }}</td>
                            <td class="px-4 py-2 text-stone-500 dark:text-stone-400" data-cell="seller">
                                <a href="{{ route('admin.sellers.show', $entry->seller) }}" class="underline">{{ $entry->seller->displayName() }}</a>
                            </td>
                            <td class="px-4 py-2 text-stone-500 dark:text-stone-400" data-cell="type">{{ $entry->type->label() }}</td>
                            <td class="px-4 py-2 text-right font-semibold tabular-nums text-stone-900 dark:text-white">{{ $entry->amount()->format() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-admin.card-list class="mt-4" caption="Every ledger entry matching the filter above">
            @foreach ($entries as $entry)
                <x-admin.card-row data-entry="{{ $entry->id }}">
                    <div class="flex items-center justify-between gap-3">
                        <span data-cell="type" class="font-medium">{{ $entry->type->label() }}</span>
                        <span class="font-semibold tabular-nums text-stone-900 dark:text-white">{{ $entry->amount()->format() }}</span>
                    </div>
                    <div class="text-stone-600 dark:text-stone-400" data-cell="seller">
                        <a href="{{ route('admin.sellers.show', $entry->seller) }}" class="underline">{{ $entry->seller->displayName() }}</a>
                    </div>
                    <div class="text-stone-600 dark:text-stone-400">{{ $entry->occurred_at?->format('M j, Y g:i A') }}</div>
                </x-admin.card-row>
            @endforeach
        </x-admin.card-list>

        <x-admin.cell-footer :shown="$entries->count()" :total="$entriesTotal" :route="route('admin.ledger')" />
    @endif
</x-layouts.admin>
