@props(['customers', 'selected' => null])

<div>
    <label for="filter-customer" class="block font-medium text-gray-700">Customer</label>
    <select id="filter-customer" name="customer" class="mt-1 rounded border border-gray-400 px-3 py-2">
        <option value="">All customers</option>
        @foreach ($customers as $customer)
            <option value="{{ $customer->id }}" @selected($selected === $customer->id)>{{ $customer->displayName() }}</option>
        @endforeach
    </select>
</div>
