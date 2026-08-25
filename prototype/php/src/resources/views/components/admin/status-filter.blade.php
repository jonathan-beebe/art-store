@props(['cases', 'selected' => null, 'label' => 'Status'])

<div>
    <label for="filter-status" class="block font-medium text-gray-700 dark:text-gray-300">{{ $label }}</label>
    <select id="filter-status" name="status" class="mt-1 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
        <option value="">All statuses</option>
        @foreach ($cases as $case)
            <option value="{{ $case->value }}" @selected($selected === $case)>{{ $case->label() }}</option>
        @endforeach
    </select>
</div>
