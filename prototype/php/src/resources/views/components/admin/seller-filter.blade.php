@props(['sellers', 'selected' => null])

<x-admin.select id="filter-seller" name="seller" label="Seller">
    <option value="">All sellers</option>
    @foreach ($sellers as $seller)
        <option value="{{ $seller->id }}" @selected($selected === $seller->id)>{{ $seller->displayName() }}</option>
    @endforeach
</x-admin.select>
