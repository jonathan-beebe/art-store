@props(['cases', 'selected'])

<div>
    <label for="filter-standing" class="block font-medium text-stone-700 dark:text-stone-300">Standing</label>
    <select id="filter-standing" name="standing" class="mt-1 rounded border border-stone-400 dark:border-stone-600 px-3 py-2">
        @foreach ($cases as $case)
            <option value="{{ $case->value }}" @selected($selected === $case)>{{ $case->label() }}</option>
        @endforeach
    </select>
</div>
