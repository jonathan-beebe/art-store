<x-layouts.admin title="Ledger — Art Store admin">
    <h1 class="text-xl font-semibold">Ledger</h1>

    <x-admin.filters :action="route('admin.ledger')">
        <x-admin.seller-filter :sellers="$sellers" :selected="$selectedSeller" />
        <x-admin.type-filter :cases="$entryTypes" :selected="$selectedType" />
    </x-admin.filters>

    <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
            <dt class="text-gray-600 dark:text-gray-400">Held</dt>
            <dd class="mt-1 text-xl font-semibold tabular-nums" data-stat="held">{{ $totals->held->format() }}</dd>
        </div>
        <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
            <dt class="text-gray-600 dark:text-gray-400">Available</dt>
            <dd class="mt-1 text-xl font-semibold tabular-nums" data-stat="available">{{ $totals->available->format() }}</dd>
        </div>
        <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
            <dt class="text-gray-600 dark:text-gray-400">Paid out</dt>
            <dd class="mt-1 text-xl font-semibold tabular-nums" data-stat="paid-out">{{ $totals->paidOut->format() }}</dd>
        </div>
        <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
            <dt class="text-gray-600 dark:text-gray-400">Refunded</dt>
            <dd class="mt-1 text-xl font-semibold tabular-nums" data-stat="refunded">{{ $totals->refunded->format() }}</dd>
        </div>
    </dl>

    @if ($entries->isEmpty())
        <x-admin.nothing class="mt-4">No ledger entries match this filter.</x-admin.nothing>
    @else
        <div class="mt-4 hidden overflow-x-auto rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 sm:block">
            <table class="w-full text-left">
                <caption class="sr-only">Every ledger entry matching the filter above</caption>
                <thead class="border-b border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th scope="col" class="px-4 py-2 font-semibold">When</th>
                        <th scope="col" class="px-4 py-2 font-semibold">Seller</th>
                        <th scope="col" class="px-4 py-2 font-semibold">Type</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @foreach ($entries as $entry)
                        <tr data-entry="{{ $entry->id }}">
                            <td class="px-4 py-2">{{ $entry->occurred_at?->format('M j, Y g:i A') }}</td>
                            <td class="px-4 py-2" data-cell="seller">
                                <a href="{{ route('admin.sellers.show', $entry->seller) }}" class="underline">{{ $entry->seller->displayName() }}</a>
                            </td>
                            <td class="px-4 py-2" data-cell="type">{{ $entry->type->label() }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $entry->amount()->format() }}</td>
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
                        <span class="tabular-nums text-gray-900 dark:text-gray-100">{{ $entry->amount()->format() }}</span>
                    </div>
                    <div class="text-gray-600 dark:text-gray-400" data-cell="seller">
                        <a href="{{ route('admin.sellers.show', $entry->seller) }}" class="underline">{{ $entry->seller->displayName() }}</a>
                    </div>
                    <div class="text-gray-600 dark:text-gray-400">{{ $entry->occurred_at?->format('M j, Y g:i A') }}</div>
                </x-admin.card-row>
            @endforeach
        </x-admin.card-list>

        <x-admin.cell-footer :shown="$entries->count()" :total="$entriesTotal" :route="route('admin.ledger')" />
    @endif
</x-layouts.admin>
