@props(['action'])

{{-- Every admin filter form is a GET back to the page it sits on, so a
     filtered view is a URL an operator can keep. --}}
<form method="GET" action="{{ $action }}" class="mt-4 flex flex-wrap items-end gap-3 rounded border border-gray-300 bg-white p-4">
    {{ $slot }}

    <button type="submit" class="rounded bg-gray-900 px-4 py-2 font-medium text-white">Filter</button>
    <a href="{{ $action }}" class="pb-2 text-gray-700 underline">Clear</a>
</form>
