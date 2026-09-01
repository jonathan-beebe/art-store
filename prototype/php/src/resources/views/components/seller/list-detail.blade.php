{{--
    Scaffolding for the seller portal's list+detail tools (Phase 2: Orders,
    Messages). A full-height flex row — a fixed-width list column with its
    own header and scrollable body, beside a detail pane that scrolls on
    its own. No page adopts this yet; it exists so Phase 2 pages share one
    shell instead of each hand-rolling the split.

    Below `lg` the two stack: the list takes the full width and the detail
    pane is hidden, unless `mobile="detail"` flips it (a detail page reached
    directly, e.g. a deep link to one order, shows the detail pane instead
    of the list on a small screen).
--}}
@props(['mobile' => 'list'])

@php
    $showListBelowLg = $mobile !== 'detail';
    $showDetailBelowLg = $mobile === 'detail';
@endphp

<div {{ $attributes->class(['flex h-full min-h-0 flex-1']) }}>
    <div class="flex w-96 shrink-0 flex-col border-r border-gray-200 dark:border-white/10 {{ $showListBelowLg ? 'flex' : 'hidden' }} lg:flex">
        @isset($listHeader)
            <div class="shrink-0 border-b border-gray-200 dark:border-white/10 px-6 py-4">
                {{ $listHeader }}
            </div>
        @endisset

        <div class="min-h-0 flex-1 overflow-y-auto">
            {{ $list }}
        </div>
    </div>

    <div class="min-w-0 flex-1 overflow-y-auto {{ $showDetailBelowLg ? 'block' : 'hidden' }} lg:block">
        {{ $slot }}
    </div>
</div>
