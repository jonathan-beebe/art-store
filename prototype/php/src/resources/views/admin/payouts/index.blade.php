<x-layouts.admin title="Payouts — Art Store admin">
    <h1 class="text-xl font-semibold">Payouts</h1>

    <x-admin.filters :action="route('admin.payouts.index')">
        <x-admin.seller-filter :sellers="$sellers" :selected="$sellerId" />
    </x-admin.filters>

    <form method="POST" action="{{ route('admin.payouts.run') }}" class="mt-4 flex flex-wrap items-end gap-3 rounded border border-gray-300 bg-white p-4">
        @csrf
        <div>
            <label for="as-of" class="block font-medium text-gray-700">Settle as of</label>
            <input id="as-of" name="as_of" type="date" value="{{ old('as_of') }}"
                   class="mt-1 rounded border border-gray-400 px-3 py-2">
            @error('as_of')
                <p class="mt-1 text-red-700">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit" class="rounded bg-gray-900 px-4 py-2 font-medium text-white">Run weekly payout</button>
        <span class="text-gray-600">Settles every seller's released escrow for the week ending before this date, or today when left blank.</span>
    </form>

    @if ($payouts->isEmpty())
        <x-admin.nothing class="mt-4">No payouts yet.</x-admin.nothing>
    @else
        <div class="mt-4 overflow-x-auto rounded border border-gray-300 bg-white">
            <table class="w-full text-left">
                <caption class="sr-only">Every weekly payout, newest period first</caption>
                <thead class="border-b border-gray-300 bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-2 font-semibold">Period</th>
                        <th scope="col" class="px-4 py-2 font-semibold">Seller</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Amount</th>
                        <th scope="col" class="px-4 py-2 font-semibold">Paid</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($payouts as $payout)
                        <tr>
                            <th scope="row" class="px-4 py-2 font-normal">
                                {{ $payout->period_start?->format('M j, Y') }} – {{ $payout->period_end?->format('M j, Y') }}
                            </th>
                            <td class="px-4 py-2">
                                <a href="{{ route('admin.sellers.show', $payout->seller) }}" class="underline">{{ $payout->seller->displayName() }}</a>
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $payout->amount()->format() }}</td>
                            <td class="px-4 py-2">{{ $payout->paid_at?->format('M j, Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.admin>
