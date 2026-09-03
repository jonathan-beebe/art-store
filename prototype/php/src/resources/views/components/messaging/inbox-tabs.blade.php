{{--
    An inbox list pane's domain tabs (docs/messaging.md § "Inbox domains"):
    underline tabs, one per domain, each a plain link. Shared by the seller
    (`accent="indigo"`) and admin (`accent="stone"`) inboxes — `domains`
    carries each portal's own vocabulary and labels (seller: Buyers/Support;
    admin: Sellers/Customers).
--}}
@props([
    'accent',
    'domain',
    'indexRoute',
    'domains',
])

@php
    // One literal Tailwind class string per token, spelled out per accent
    // rather than built from `$accent` at runtime — the build's class scan
    // only sees names that appear whole in the source (pane-row.blade.php
    // follows the same rule).
    $palette = $accent === 'indigo' ? [
        'tabRow' => 'border-b border-gray-200 dark:border-white/10',
        'tabActive' => 'border-b-2 border-indigo-500 px-1 pb-3 text-sm/5 font-medium whitespace-nowrap text-gray-900 dark:text-white',
        'tabInactive' => 'border-b-2 border-transparent px-1 pb-3 text-sm/5 font-medium whitespace-nowrap text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-white/20 dark:hover:text-gray-200',
    ] : [
        'tabRow' => 'border-b border-stone-200 dark:border-stone-800',
        'tabActive' => 'border-b-2 border-stone-900 px-1 pb-3 text-sm/5 font-medium whitespace-nowrap text-stone-900 dark:border-white dark:text-white',
        'tabInactive' => 'border-b-2 border-transparent px-1 pb-3 text-sm/5 font-medium whitespace-nowrap text-stone-500 hover:border-stone-300 hover:text-stone-700 dark:text-stone-400 dark:hover:border-white/20 dark:hover:text-stone-200',
    ];
@endphp

<nav aria-label="Domain" class="flex space-x-6 {{ $palette['tabRow'] }}">
    @foreach ($domains as $value => $label)
        @php $isActive = $domain === $value; @endphp
        <a
            href="{{ route($indexRoute, ['domain' => $value]) }}"
            @if ($isActive) aria-current="page" @endif
            class="{{ $isActive ? $palette['tabActive'] : $palette['tabInactive'] }}"
        >{{ $label }}</a>
    @endforeach
</nav>
