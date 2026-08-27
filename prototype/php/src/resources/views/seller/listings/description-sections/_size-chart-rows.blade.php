{{--
    Label + two value columns for a Size chart section.
    $rows: list<array{label: string, value1: string, value2: string}>,
    already padded with blank rows for a JS-off "add a row".
    $idPrefix: makes each input's id unique when this partial is included
    more than once on the same page (one card per section).
--}}
<div class="overflow-x-auto">
    <table class="w-full border-collapse text-sm">
        <thead>
            <tr class="bg-gray-50 dark:bg-gray-800 text-left">
                <th class="p-2 font-medium text-gray-700 dark:text-gray-300">Label</th>
                <th class="p-2 font-medium text-gray-700 dark:text-gray-300">Value</th>
                <th class="p-2 font-medium text-gray-700 dark:text-gray-300">Value</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $i => $row)
                <tr class="border-b border-gray-100 dark:border-gray-800">
                    <td class="p-1">
                        <label for="size-chart-{{ $idPrefix }}-label-{{ $i }}" class="sr-only">Label</label>
                        <input id="size-chart-{{ $idPrefix }}-label-{{ $i }}" type="text" name="size_chart[{{ $i }}][label]" value="{{ old("size_chart.$i.label", $row['label'] ?? '') }}" placeholder="S" class="w-full rounded border border-gray-400 dark:border-gray-600 px-2 py-1">
                    </td>
                    <td class="p-1">
                        <label for="size-chart-{{ $idPrefix }}-value1-{{ $i }}" class="sr-only">Value</label>
                        <input id="size-chart-{{ $idPrefix }}-value1-{{ $i }}" type="text" name="size_chart[{{ $i }}][value1]" value="{{ old("size_chart.$i.value1", $row['value1'] ?? '') }}" placeholder="36 in" class="w-full rounded border border-gray-400 dark:border-gray-600 px-2 py-1">
                    </td>
                    <td class="p-1">
                        <label for="size-chart-{{ $idPrefix }}-value2-{{ $i }}" class="sr-only">Value</label>
                        <input id="size-chart-{{ $idPrefix }}-value2-{{ $i }}" type="text" name="size_chart[{{ $i }}][value2]" value="{{ old("size_chart.$i.value2", $row['value2'] ?? '') }}" placeholder="27 in" class="w-full rounded border border-gray-400 dark:border-gray-600 px-2 py-1">
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
