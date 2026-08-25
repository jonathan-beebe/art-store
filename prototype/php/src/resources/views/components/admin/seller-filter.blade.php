@props(['sellers', 'selected' => null])

<div>
    <label for="filter-seller" class="block font-medium text-gray-700 dark:text-gray-300">Seller</label>
    <select id="filter-seller" name="seller" class="mt-1 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
        <option value="">All sellers</option>
        @foreach ($sellers as $seller)
            <option value="{{ $seller->id }}" @selected($selected === $seller->id)>{{ $seller->displayName() }}</option>
        @endforeach
    </select>
</div>
