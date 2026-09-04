@props(['cases', 'selected'])

<x-admin.select id="filter-standing" name="standing" label="Standing">
    @foreach ($cases as $case)
        <option value="{{ $case->value }}" @selected($selected === $case)>{{ $case->label() }}</option>
    @endforeach
</x-admin.select>
