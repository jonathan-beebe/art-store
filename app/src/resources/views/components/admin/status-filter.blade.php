@props(['cases', 'selected' => null, 'label' => 'Status'])

<x-admin.select id="filter-status" name="status" :label="$label">
    <option value="">All statuses</option>
    @foreach ($cases as $case)
        <option value="{{ $case->value }}" @selected($selected === $case)>{{ $case->label() }}</option>
    @endforeach
</x-admin.select>
