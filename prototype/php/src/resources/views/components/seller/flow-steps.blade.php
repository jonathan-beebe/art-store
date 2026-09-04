@props(['fulfillment', 'steps', 'progress', 'canComplete' => false])

@php
    $next = $progress->next();
@endphp

<div class="mt-2 rounded-lg border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
    @if ($steps === [])
        <p class="p-4 text-sm text-gray-500 dark:text-gray-400">
            Your flow has no steps.
            <a href="{{ route('seller.orders.flow.edit') }}" class="font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">Add some</a>
        </p>
    @else
        <ul role="list" class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach ($steps as $step)
                @php
                    $isDone = $progress->hasCompleted($step);
                    $isNext = $next !== null && $next->id === $step->id;
                @endphp
                <li class="flex flex-wrap items-center gap-4 p-4">
                    <span @class([
                        'flex size-8 flex-none items-center justify-center rounded-full',
                        'bg-indigo-600 text-white' => $isDone,
                        'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-400' => ! $isDone,
                    ])>
                        @if ($isDone)
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true"><path d="m4.5 12.75 6 6 9-13.5" /></svg>
                        @else
                            <span class="text-xs font-semibold">{{ $loop->iteration }}</span>
                        @endif
                    </span>

                    <div class="min-w-0 flex-1">
                        <p @class([
                            'text-sm font-medium',
                            'text-gray-900 dark:text-white' => $isDone || $isNext,
                            'text-gray-500 dark:text-gray-400' => ! $isDone && ! $isNext,
                        ])>{{ $step->label }}</p>
                        @if ($isDone)
                            <p class="text-xs/5 text-gray-500 dark:text-gray-400">Done</p>
                        @elseif ($isNext)
                            <p class="text-xs/5 text-gray-500 dark:text-gray-400">Next</p>
                        @endif
                    </div>

                    @if ($isNext && $canComplete)
                        <form method="POST" action="{{ route('seller.orders.steps.complete', [$fulfillment->id, $step->id]) }}" class="flex flex-wrap items-end gap-3">
                            @csrf

                            @if ($step->printsLabel())
                                <div class="w-40">
                                    <label for="step-carrier" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Carrier</label>
                                    <input id="step-carrier" name="carrier" type="text" required maxlength="255" value="{{ old('carrier') }}" placeholder="Owl Post"
                                           class="mt-1 block w-full rounded-md bg-white px-3 py-1.5 text-sm text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10">
                                </div>
                                <div class="w-56">
                                    <label for="step-tracking" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tracking number</label>
                                    <input id="step-tracking" name="tracking_number" type="text" required maxlength="255" value="{{ old('tracking_number') }}"
                                           class="mt-1 block w-full rounded-md bg-white px-3 py-1.5 text-sm text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10">
                                </div>
                            @endif

                            <button type="submit" class="min-h-11 rounded-md bg-indigo-600 px-2.5 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">{{ $step->action->control() }}</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
