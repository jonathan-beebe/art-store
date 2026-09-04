@props(['customers', 'selected' => null])

<x-admin.select id="filter-customer" name="customer" label="Customer">
    <option value="">All customers</option>
    @foreach ($customers as $customer)
        <option value="{{ $customer->id }}" @selected($selected === $customer->id)>{{ $customer->displayName() }}</option>
    @endforeach
</x-admin.select>
