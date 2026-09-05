@props(['action'])

{{-- Every admin filter form is a GET back to the page it sits on, so a
     filtered view is a URL an operator can keep. --}}
<form method="GET" action="{{ $action }}" class="mt-4 flex flex-wrap items-end gap-3 rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4">
    {{ $slot }}

    <x-admin.button-primary>Filter</x-admin.button-primary>
    <x-admin.clear-link :href="$action" />
</form>
