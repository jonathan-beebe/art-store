<x-layouts.admin title="Ledger — Art Store admin">
    <h1 class="text-xl font-semibold">Ledger</h1>

    <x-admin.filters :action="route('admin.ledger')">
        <x-admin.seller-filter :sellers="$sellers" :selected="$selectedSeller" />
        <x-admin.type-filter :cases="$entryTypes" :selected="$selectedType" />
    </x-admin.filters>

    <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded border border-gray-300 bg-white p-4">
            <dt class="text-gray-600">Held</dt>
            <dd class="mt-1 text-xl font-semibold tabular-nums" data-stat="held">{{ $totals->held->format() }}</dd>
        </div>
        <div class="rounded border border-gray-300 bg-white p-4">
            <dt class="text-gray-600">Available</dt>
            <dd class="mt-1 text-xl font-semibold tabular-nums" data-stat="available">{{ $totals->available->format() }}</dd>
        </div>
        <div class="rounded border border-gray-300 bg-white p-4">
            <dt class="text-gray-600">Paid out</dt>
            <dd class="mt-1 text-xl font-semibold tabular-nums" data-stat="paid-out">{{ $totals->paidOut->format() }}</dd>
        </div>
        <div class="rounded border border-gray-300 bg-white p-4">
            <dt class="text-gray-600">Refunded</dt>
            <dd class="mt-1 text-xl font-semibold tabular-nums" data-stat="refunded">{{ $totals->refunded->format() }}</dd>
        </div>
    </dl>

    @if ($entries->isEmpty())
        <x-admin.nothing class="mt-4">No ledger entries match this filter.</x-admin.nothing>
    @else
        <div class="mt-4 overflow-x-auto rounded border border-gray-300 bg-white">
            <table class="w-full text-left">
                <caption class="sr-only">Every ledger entry matching the filter above</caption>
                <thead class="border-b border-gray-300 bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-2 font-semibold">When</th>
                        <th scope="col" class="px-4 py-2 font-semibold">Seller</th>
                        <th scope="col" class="px-4 py-2 font-semibold">Type</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
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
    @endif
</x-layouts.admin>
