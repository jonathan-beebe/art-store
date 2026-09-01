@props(['cases', 'selected' => null, 'label' => 'Status'])

<div>
    <label for="filter-status" class="block font-medium text-stone-700 dark:text-stone-300">{{ $label }}</label>
    <select id="filter-status" name="status" class="mt-1 rounded-md bg-white px-3 py-2 text-stone-900 inset-ring inset-ring-stone-300 focus:outline-2 focus:-outline-offset-2 focus:outline-stone-600 dark:bg-white/5 dark:text-stone-100 dark:inset-ring-white/10">
        <option value="">All statuses</option>
        @foreach ($cases as $case)
            <option value="{{ $case->value }}" @selected($selected === $case)>{{ $case->label() }}</option>
        @endforeach
    </select>
</div>
