@props(['action'])

{{-- Every admin filter form is a GET back to the page it sits on, so a
     filtered view is a URL an operator can keep. --}}
<form method="GET" action="{{ $action }}" class="mt-4 flex flex-wrap items-end gap-3 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
    {{ $slot }}

    <button type="submit" class="inline-flex min-h-11 items-center rounded bg-gray-900 dark:bg-gray-100 px-4 font-medium text-white dark:text-gray-900">Filter</button>
    <a href="{{ $action }}" class="inline-flex min-h-11 items-center text-gray-700 dark:text-gray-300 underline">Clear</a>
</form>
