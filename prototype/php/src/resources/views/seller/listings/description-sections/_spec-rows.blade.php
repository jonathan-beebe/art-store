{{--
    Label/value rows for a Details list section.
    $rows: list<array{label: string, value: string}>, already padded with
    blank rows for a JS-off "add a row".
    $idPrefix: makes each input's id unique when this partial is included
    more than once on the same page (one card per section).
--}}
<div>
    <span class="block font-medium text-gray-700 dark:text-gray-300">Rows</span>
    <div class="mt-1 flex flex-col gap-2">
        @foreach ($rows as $i => $row)
            <div class="flex flex-wrap gap-2">
                <label for="spec-{{ $idPrefix }}-label-{{ $i }}" class="sr-only">Label</label>
                <input id="spec-{{ $idPrefix }}-label-{{ $i }}" type="text" name="spec[{{ $i }}][label]" value="{{ old("spec.$i.label", $row['label'] ?? '') }}" placeholder="Label, like Material" class="rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                <label for="spec-{{ $idPrefix }}-value-{{ $i }}" class="sr-only">Value</label>
                <input id="spec-{{ $idPrefix }}-value-{{ $i }}" type="text" name="spec[{{ $i }}][value]" value="{{ old("spec.$i.value", $row['value'] ?? '') }}" placeholder="Value, like Cotton" class="rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
            </div>
        @endforeach
    </div>
</div>
