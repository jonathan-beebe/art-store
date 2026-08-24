@props(['cases', 'selected' => null, 'label' => 'Type'])

<div>
    <label for="filter-type" class="block font-medium text-gray-700">{{ $label }}</label>
    <select id="filter-type" name="type" class="mt-1 rounded border border-gray-400 px-3 py-2">
        <option value="">All types</option>
        @foreach ($cases as $case)
            <option value="{{ $case->value }}" @selected($selected === $case)>{{ $case->label() }}</option>
        @endforeach
    </select>
</div>
