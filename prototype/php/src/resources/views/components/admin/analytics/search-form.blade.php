{{-- One ring holding the magnifier, the text input, and an attached
     submit button — the same height as the segmented pills beside it.
     `carry` is a page's round-tripped query parameters; `q` is excluded
     since the visible input supplies it. --}}
@props(['action', 'search', 'placeholder', 'id', 'carry' => []])

<form method="GET" action="{{ $action }}" {{ $attributes->merge(['class' => 'flex min-w-0 flex-1 items-center rounded-md bg-white dark:bg-white/5 inset-ring inset-ring-stone-300 dark:inset-ring-white/10 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-stone-600']) }}>
    @foreach (collect($carry)->except('q') as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="pl-2.5 text-stone-400" aria-hidden="true"><path d="M21 21l-4.35-4.35M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16z"></path></svg>
    <label for="{{ $id }}" class="sr-only">{{ $placeholder }}</label>
    <input id="{{ $id }}" name="q" type="text" value="{{ $search }}" placeholder="{{ $placeholder }}" class="min-w-0 flex-1 border-0 bg-transparent py-1.5 text-sm text-stone-900 dark:text-stone-100 placeholder:text-stone-400 focus:outline-0">
    <button type="submit" class="rounded-r-md border-l border-stone-200 dark:border-white/10 px-2.5 py-1.5 text-xs font-medium text-stone-600 dark:text-stone-400 hover:bg-stone-100 dark:hover:bg-white/10">Search</button>
</form>
