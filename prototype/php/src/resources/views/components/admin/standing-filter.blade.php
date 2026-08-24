@props(['cases', 'selected'])

<div>
    <label for="filter-standing" class="block font-medium text-gray-700">Standing</label>
    <select id="filter-standing" name="standing" class="mt-1 rounded border border-gray-400 px-3 py-2">
        @foreach ($cases as $case)
            <option value="{{ $case->value }}" @selected($selected === $case)>{{ $case->label() }}</option>
        @endforeach
    </select>
</div>
