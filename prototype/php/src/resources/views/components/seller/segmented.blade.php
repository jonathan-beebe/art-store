{{--
    A segmented control: one link per choice, joined into a single pill,
    the choice in force carrying the accent and `aria-current`. Takes a
    `list<App\Seller\SegmentLink>` — every href is built by the class that
    knows the route, so this stays a renderer.
--}}
@props(['links', 'label'])

<div role="group" aria-label="{{ $label }}" class="inline-flex isolate rounded-md">
    @foreach ($links as $link)
        <a
            href="{{ $link->href }}"
            @if ($link->active) aria-current="true" @endif
            @class([
                'relative inline-flex items-center px-3 py-1.5 text-xs font-medium -ml-px first:ml-0 first:rounded-l-md last:rounded-r-md inset-ring focus-visible:z-10 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600',
                'z-10 bg-indigo-50 text-indigo-700 inset-ring-indigo-300 dark:bg-indigo-500/20 dark:text-indigo-300 dark:inset-ring-indigo-400/30' => $link->active,
                'bg-white text-gray-600 inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-gray-400 dark:inset-ring-white/10 dark:hover:bg-white/20' => ! $link->active,
            ])
        >{{ $link->label }}</a>
    @endforeach
</div>
