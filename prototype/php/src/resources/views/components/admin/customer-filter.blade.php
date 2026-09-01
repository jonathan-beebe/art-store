@props(['customers', 'selected' => null])

<div>
    <label for="filter-customer" class="block font-medium text-stone-700 dark:text-stone-300">Customer</label>
    <select id="filter-customer" name="customer" class="mt-1 rounded-md bg-white px-3 py-2 text-stone-900 inset-ring inset-ring-stone-300 focus:outline-2 focus:-outline-offset-2 focus:outline-stone-600 dark:bg-white/5 dark:text-stone-100 dark:inset-ring-white/10">
        <option value="">All customers</option>
        @foreach ($customers as $customer)
            <option value="{{ $customer->id }}" @selected($selected === $customer->id)>{{ $customer->displayName() }}</option>
        @endforeach
    </select>
</div>
