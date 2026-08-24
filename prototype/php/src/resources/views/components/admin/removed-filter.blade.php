@props(['cases', 'selected'])

<div>
    <label for="filter-removed" class="block font-medium text-gray-700">Removed</label>
    <select id="filter-removed" name="removed" class="mt-1 rounded border border-gray-400 px-3 py-2">
        @foreach ($cases as $case)
            <option value="{{ $case->value }}" @selected($selected === $case)>{{ $case->label() }}</option>
        @endforeach
    </select>
</div>
