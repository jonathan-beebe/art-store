@props(['feed', 'empty' => 'Nothing has happened here yet.'])

@php
    $events = $feed->events;
    $last = count($events) - 1;
@endphp

@if ($feed->isEmpty())
    <p class="p-6 text-sm text-gray-500 dark:text-gray-400">{{ $empty }}</p>
@else
    <ul role="list" class="space-y-6">
        @foreach ($events as $index => $event)
            <li data-feed-row class="relative flex gap-x-4">
                @if ($index !== $last)
                    <div data-rail class="absolute top-0 -bottom-6 left-0 flex w-8 justify-center">
                        <div class="w-px bg-gray-200 dark:bg-white/10"></div>
                    </div>
                @endif

                <span
                    data-accent="{{ $event->isAccented() ? 'true' : 'false' }}"
                    @class([
                        'relative flex size-8 flex-none items-center justify-center rounded-full ring-4 ring-white dark:ring-gray-900',
                        'bg-indigo-600 text-white' => $event->isAccented(),
                        'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-400' => ! $event->isAccented(),
                    ])>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true">
                        <path d="{{ \App\Seller\FeedIconPath::of($event->icon) }}" />
                    </svg>
                </span>

                <div class="flex-auto py-0.5 text-sm/6 text-gray-500 dark:text-gray-400">
                    <p>
                        <span data-feed-actor class="font-medium text-gray-900 dark:text-white">{{ $event->actor }}</span>
                        @if ($event->link)
                            <a href="{{ $event->link }}" class="hover:text-gray-700 dark:hover:text-gray-300">{{ $event->text }}</a>
                        @else
                            {{ $event->text }}
                        @endif
                    </p>
                    @if ($event->quote)
                        <p data-feed-quote class="mt-1 rounded-md bg-white p-3 text-sm/6 text-gray-700 inset-ring inset-ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:inset-ring-white/10">{{ $event->quote }}</p>
                    @endif
                </div>

                <time datetime="{{ $event->occurredAt->format(DATE_ATOM) }}" class="flex-none py-0.5 text-xs/5 text-gray-500 dark:text-gray-400">{{ $event->occurredAt->format('M j · H:i') }}</time>
            </li>
        @endforeach
    </ul>
@endif
