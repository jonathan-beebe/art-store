@props(['cases', 'selected' => null, 'label' => 'Type'])

<x-admin.select id="filter-type" name="type" :label="$label">
    <option value="">All types</option>
    @foreach ($cases as $case)
        <option value="{{ $case->value }}" @selected($selected === $case)>{{ $case->label() }}</option>
    @endforeach
</x-admin.select>
