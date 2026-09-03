@php
    $segmentActive = 'rounded-md px-2.5 py-1 text-xs font-medium bg-stone-900 dark:bg-stone-100 text-white dark:text-stone-900';
    $segmentIdle = 'rounded-md px-2.5 py-1 text-xs font-medium bg-stone-100 dark:bg-stone-400/10 text-stone-600 dark:text-stone-400 hover:bg-stone-200 dark:hover:bg-stone-400/20';
@endphp

<x-layouts.admin :title="$funnel->name.' — Art Store admin'">
    <p><a href="{{ route('admin.analytics.index') }}" class="text-stone-700 dark:text-stone-300 underline">&larr; Analytics</a></p>

    <div class="mt-2 flex flex-wrap items-baseline gap-3">
        <h1 class="text-xl font-semibold">{{ $funnel->name }}</h1>
        <span class="text-stone-600 dark:text-stone-400">{{ $rangeCaption }}</span>
        <a href="{{ route('admin.funnels.edit', $funnel) }}" class="ml-auto text-stone-700 dark:text-stone-300 underline">Edit funnel</a>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-3 rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4">
        <div role="group" aria-label="Range" class="flex gap-1">
            @foreach ($rangeLinks as $link)
                <a href="{{ $link['href'] }}" @if ($link['active']) aria-current="true" @endif class="{{ $link['active'] ? $segmentActive : $segmentIdle }}">{{ $link['label'] }}</a>
            @endforeach
        </div>
    </div>

    <section aria-labelledby="funnel-detail-heading" class="mt-6">
        <h2 id="funnel-detail-heading" class="font-semibold text-stone-700 dark:text-stone-300">Funnel</h2>

        <x-admin.analytics.funnel :funnel="$funnelView" />
    </section>
</x-layouts.admin>
