{{--
    An inbox list pane's header row (docs/messaging.md § "Inbox filters and
    the seller's queue"): underline tabs pick the domain on the left, one
    Filter popover holds the Type and Status checkbox groups on the right.
    Shared by the seller (`accent="indigo"`) and admin (`accent="stone"`)
    inboxes — `domains` and `statuses` carry each portal's own vocabulary and
    labels, since the two disagree (seller: Buyers/Support, no Needs reply;
    admin: Sellers/Customers, Needs reply with its own count). Type is fixed
    across both (Questions/Orders/Support), so it lives here rather than as a
    prop. The popover is a plain `<details>`/`<form method="GET">` — every
    choice in it still narrows the inbox with JavaScript disabled.
--}}
@props([
    'accent',
    'query',
    'indexRoute',
    'domains',
    'statuses',
    'defaultStatuses',
    'needsReplyCount' => null,
])

@php
    $types = ['questions' => 'Questions', 'orders' => 'Orders', 'support' => 'Support'];
    $changeCount = $query->changesFromDefault(array_keys($types), $defaultStatuses);

    // One literal Tailwind class string per token, spelled out per accent
    // rather than built from `$accent` at runtime — the build's class scan
    // only sees names that appear whole in the source (pane-row.blade.php
    // follows the same rule).
    $palette = $accent === 'indigo' ? [
        'tabRow' => 'border-b border-gray-200 dark:border-white/10',
        'tabActive' => 'border-b-2 border-indigo-500 px-1 pb-3 text-sm/5 font-medium whitespace-nowrap text-gray-900 dark:text-white',
        'tabInactive' => 'border-b-2 border-transparent px-1 pb-3 text-sm/5 font-medium whitespace-nowrap text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-white/20 dark:hover:text-gray-200',
        'summaryMuted' => 'text-gray-500 dark:text-gray-400',
        'summaryTitle' => 'text-gray-900 dark:text-white',
        'summaryOpen' => 'group-open/filter:text-gray-900 dark:group-open/filter:text-white',
        'countPill' => 'bg-indigo-600 text-white',
        'panel' => 'bg-white ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-white/10',
        'legend' => 'text-gray-500 dark:text-gray-400',
        'label' => 'text-gray-700 dark:text-gray-300',
        'meta' => 'text-gray-500 dark:text-gray-400',
        'divider' => 'border-gray-200 dark:border-white/10',
        'checkbox' => 'col-start-1 row-start-1 appearance-none rounded-sm border border-gray-300 bg-white checked:border-indigo-600 checked:bg-indigo-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:border-white/10 dark:bg-white/5 dark:checked:border-indigo-500 dark:checked:bg-indigo-500 dark:focus-visible:outline-indigo-500',
        'reset' => 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200',
        'done' => 'rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600',
    ] : [
        'tabRow' => 'border-b border-stone-200 dark:border-stone-800',
        'tabActive' => 'border-b-2 border-stone-900 px-1 pb-3 text-sm/5 font-medium whitespace-nowrap text-stone-900 dark:border-white dark:text-white',
        'tabInactive' => 'border-b-2 border-transparent px-1 pb-3 text-sm/5 font-medium whitespace-nowrap text-stone-500 hover:border-stone-300 hover:text-stone-700 dark:text-stone-400 dark:hover:border-white/20 dark:hover:text-stone-200',
        'summaryMuted' => 'text-stone-500 dark:text-stone-400',
        'summaryTitle' => 'text-stone-900 dark:text-white',
        'summaryOpen' => 'group-open/filter:text-stone-900 dark:group-open/filter:text-white',
        'countPill' => 'bg-stone-700 text-white',
        'panel' => 'bg-white ring-1 ring-stone-200 dark:bg-stone-800 dark:ring-white/10',
        'legend' => 'text-stone-500 dark:text-stone-400',
        'label' => 'text-stone-700 dark:text-stone-300',
        'meta' => 'text-stone-500 dark:text-stone-400',
        'divider' => 'border-stone-200 dark:border-stone-800',
        'checkbox' => 'col-start-1 row-start-1 appearance-none rounded-sm border border-stone-300 bg-white checked:border-stone-700 checked:bg-stone-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-600 dark:border-white/10 dark:bg-white/5 dark:checked:border-stone-500 dark:checked:bg-stone-500 dark:focus-visible:outline-stone-500',
        'reset' => 'text-stone-600 hover:text-stone-900 dark:text-stone-400 dark:hover:text-stone-200',
        'done' => 'rounded-md bg-stone-700 px-3 py-1.5 text-white hover:bg-stone-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-600',
    ];
@endphp

<div class="flex items-end justify-between gap-4 {{ $palette['tabRow'] }}">
    <nav aria-label="Domain" class="-mb-px flex space-x-6">
        @foreach ($domains as $value => $label)
            @php $isActive = $query->domain === $value; @endphp
            <a
                href="{{ route($indexRoute, [...$query->toRouteParams(), 'domain' => $value]) }}"
                @if ($isActive) aria-current="page" @endif
                class="{{ $isActive ? $palette['tabActive'] : $palette['tabInactive'] }}"
            >{{ $label }}</a>
        @endforeach
    </nav>

    <details class="group/filter relative mb-3 shrink-0">
        <summary class="flex cursor-pointer list-none items-center gap-1.5 text-sm/5 font-medium marker:content-none [&::-webkit-details-marker]:hidden {{ $changeCount > 0 ? $palette['summaryTitle'] : $palette['summaryMuted'] }} {{ $palette['summaryOpen'] }}">
            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                <path fill-rule="evenodd" d="M2.628 1.601C5.028 1.206 7.49 1 10 1s4.973.206 7.372.601a.75.75 0 0 1 .628.74v2.288a2.25 2.25 0 0 1-.659 1.59l-4.682 4.683a2.25 2.25 0 0 0-.659 1.59v3.037c0 .684-.31 1.33-.844 1.757l-1.937 1.55A.75.75 0 0 1 8 18.25v-5.757a2.25 2.25 0 0 0-.659-1.591L2.659 6.22A2.25 2.25 0 0 1 2 4.629V2.34a.75.75 0 0 1 .628-.74Z" clip-rule="evenodd" />
            </svg>
            Filter
            @if ($changeCount > 0)
                <span class="inline-flex min-w-[1.25rem] items-center justify-center rounded-full px-1.5 py-0.5 text-xs font-semibold {{ $palette['countPill'] }}">{{ $changeCount }}</span>
            @endif
        </summary>

        <div class="absolute right-0 z-10 mt-2 w-60 rounded-lg p-4 shadow-2xl {{ $palette['panel'] }}">
            <form method="GET" action="{{ route($indexRoute) }}">
                <input type="hidden" name="domain" value="{{ $query->domain }}">

                <fieldset>
                    <legend class="text-xs/5 font-semibold tracking-wide uppercase {{ $palette['legend'] }}">Type</legend>
                    <div class="mt-2">
                        @foreach ($types as $value => $label)
                            <label class="flex items-center gap-3 py-1.5 text-sm/5 {{ $palette['label'] }}">
                                <div class="group/box grid size-4 shrink-0 grid-cols-1">
                                    <input type="checkbox" name="type[]" value="{{ $value }}" @checked($query->hasType($value)) class="{{ $palette['checkbox'] }}">
                                    <svg viewBox="0 0 14 14" fill="none" aria-hidden="true" class="pointer-events-none col-start-1 row-start-1 size-3.5 self-center justify-self-center stroke-white">
                                        <path d="M3 8L6 11L11 3.5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="opacity-0 group-has-checked/box:opacity-100" />
                                    </svg>
                                </div>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="my-3 border-t {{ $palette['divider'] }}"></div>

                <fieldset>
                    <legend class="text-xs/5 font-semibold tracking-wide uppercase {{ $palette['legend'] }}">Status</legend>
                    <div class="mt-2">
                        @foreach ($statuses as $value => $label)
                            <label class="flex items-center gap-3 py-1.5 text-sm/5 {{ $palette['label'] }}">
                                <div class="group/box grid size-4 shrink-0 grid-cols-1">
                                    <input type="checkbox" name="status[]" value="{{ $value }}" @checked($query->hasStatus($value)) class="{{ $palette['checkbox'] }}">
                                    <svg viewBox="0 0 14 14" fill="none" aria-hidden="true" class="pointer-events-none col-start-1 row-start-1 size-3.5 self-center justify-self-center stroke-white">
                                        <path d="M3 8L6 11L11 3.5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="opacity-0 group-has-checked/box:opacity-100" />
                                    </svg>
                                </div>
                                <span class="flex flex-1 items-center justify-between gap-2">
                                    <span>{{ $label }}</span>
                                    @if ($value === 'needs-reply' && $needsReplyCount !== null)
                                        <span class="{{ $palette['meta'] }}">{{ $needsReplyCount }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="mt-2 flex items-center justify-between border-t pt-2 {{ $palette['divider'] }}">
                    <a href="{{ route($indexRoute, $query->resetRouteParams()) }}" class="text-sm font-medium {{ $palette['reset'] }}">Reset</a>
                    <button type="submit" class="text-sm font-medium {{ $palette['done'] }}">Done</button>
                </div>
            </form>
        </div>
    </details>
</div>
