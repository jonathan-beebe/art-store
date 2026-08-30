<x-layouts.admin title="Payouts — Art Store admin">
    <h1 class="text-xl font-semibold">Payouts</h1>

    <x-admin.filters :action="route('admin.payouts.index')">
        <x-admin.seller-filter :sellers="$sellers" :selected="$sellerId" />
    </x-admin.filters>

    <form method="POST" action="{{ route('admin.payouts.run') }}" class="mt-4 flex flex-wrap items-end gap-3 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
        @csrf
        <div>
            <label for="as-of" class="block font-medium text-gray-700 dark:text-gray-300">Settle as of</label>
            <input id="as-of" name="as_of" type="date" value="{{ old('as_of') }}"
                   class="mt-1 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
            @error('as_of')
                <p class="mt-1 text-red-700 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit" class="block w-full rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 text-center font-medium text-white dark:text-gray-900 sm:inline-block sm:w-auto">Run weekly payout</button>
        <span class="text-gray-600 dark:text-gray-400">Settles every seller's released escrow for the week ending before this date, or today when left blank.</span>
    </form>

    <x-admin.payouts-table :payouts="$payouts" caption="Every weekly payout, newest period first" />
</x-layouts.admin>
