@props(['cases', 'selected'])

<div>
    <label for="filter-removed" class="block font-medium text-stone-700 dark:text-stone-300">Removed</label>
    <select id="filter-removed" name="removed" class="mt-1 rounded-md bg-white px-3 py-2 text-stone-900 inset-ring inset-ring-stone-300 focus:outline-2 focus:-outline-offset-2 focus:outline-stone-600 dark:bg-white/5 dark:text-stone-100 dark:inset-ring-white/10">
        @foreach ($cases as $case)
            <option value="{{ $case->value }}" @selected($selected === $case)>{{ $case->label() }}</option>
        @endforeach
    </select>
</div>
