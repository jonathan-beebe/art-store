@props(['sellers', 'selected' => null])

<div>
    <label for="filter-seller" class="block font-medium text-stone-700 dark:text-stone-300">Seller</label>
    <select id="filter-seller" name="seller" class="mt-1 rounded-md bg-white px-3 py-2 text-stone-900 inset-ring inset-ring-stone-300 focus:outline-2 focus:-outline-offset-2 focus:outline-stone-600 dark:bg-white/5 dark:text-stone-100 dark:inset-ring-white/10">
        <option value="">All sellers</option>
        @foreach ($sellers as $seller)
            <option value="{{ $seller->id }}" @selected($selected === $seller->id)>{{ $seller->displayName() }}</option>
        @endforeach
    </select>
</div>
