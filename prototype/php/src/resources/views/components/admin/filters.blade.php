@props(['action'])

{{-- Every admin filter form is a GET back to the page it sits on, so a
     filtered view is a URL an operator can keep. --}}
<form method="GET" action="{{ $action }}" class="mt-4 flex flex-wrap items-end gap-3 rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4">
    {{ $slot }}

    <button type="submit" class="inline-flex min-h-11 items-center rounded-md bg-stone-700 px-4 font-medium text-white hover:bg-stone-600">Filter</button>
    <a href="{{ $action }}" class="inline-flex min-h-11 items-center text-stone-700 dark:text-stone-300 underline">Clear</a>
</form>
