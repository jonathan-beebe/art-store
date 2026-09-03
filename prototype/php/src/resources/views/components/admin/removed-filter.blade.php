@props(['cases', 'selected'])

<x-admin.select id="filter-removed" name="removed" label="Removed">
    @foreach ($cases as $case)
        <option value="{{ $case->value }}" @selected($selected === $case)>{{ $case->label() }}</option>
    @endforeach
</x-admin.select>
