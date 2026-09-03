{{-- A funnel's name and its ordered step list, editable with plain form
     posts — no JavaScript. Every button beyond "Save" carries its own
     `op` and posts back to the same `$action`; the controller reads it as
     an editor action, not a save, and re-renders this same form with the
     step list `App\Support\Admin\FunnelStepListOp` produces.
     `$name`/`$steps` are the form's current values; `$eventNames` is
     `AnalyticsEventName::cases()`. --}}
@props(['action', 'method', 'name', 'steps', 'eventNames', 'submitLabel'])

<form method="POST" action="{{ $action }}" class="mt-4 max-w-2xl rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4">
    @csrf
    @if ($method === 'PUT')
        @method('PUT')
    @endif

    <div>
        <label for="funnel-name" class="block font-medium text-stone-900 dark:text-stone-100">Name</label>
        <input id="funnel-name" name="name" type="text" required maxlength="255" value="{{ $name }}"
               class="mt-1 block w-full rounded-md bg-white px-3 py-2 text-stone-900 inset-ring inset-ring-stone-300 focus:outline-2 focus:-outline-offset-2 focus:outline-stone-600 dark:bg-white/5 dark:text-stone-100 dark:inset-ring-white/10">
        @error('name')
            <p class="mt-1 text-red-700 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div class="mt-4">
        <span class="block font-medium text-stone-900 dark:text-stone-100">Steps</span>
        <p class="mt-1 text-stone-600 dark:text-stone-400">Visitors is always the first step. Add two or more from the list below, in the order a shopper moves through them.</p>

        <div class="mt-2 flex flex-col gap-2">
            @foreach ($steps as $index => $step)
                <div class="flex items-center gap-2">
                    <label for="funnel-step-{{ $index }}" class="sr-only">Step {{ $index + 1 }}</label>
                    <select id="funnel-step-{{ $index }}" name="steps[]"
                            class="block w-full rounded-md bg-white px-3 py-2 text-stone-900 inset-ring inset-ring-stone-300 focus:outline-2 focus:-outline-offset-2 focus:outline-stone-600 dark:bg-white/5 dark:text-stone-100 dark:inset-ring-white/10">
                        <option value="" @selected($step === '')>Choose an event&hellip;</option>
                        @foreach ($eventNames as $eventName)
                            <option value="{{ $eventName->value }}" @selected($step === $eventName->value)>{{ $eventName->pluralLabel() }}</option>
                        @endforeach
                    </select>

                    <button type="submit" name="op" value="move_up:{{ $index }}" formnovalidate @disabled($index === 0)
                            class="inline-flex items-center rounded-md bg-white dark:bg-white/10 px-2 py-1.5 text-sm font-semibold text-stone-900 dark:text-white shadow-xs inset-ring inset-ring-stone-300 dark:inset-ring-white/5 hover:bg-stone-50 dark:hover:bg-white/20 disabled:opacity-40">
                        &uarr;<span class="sr-only">Move step {{ $index + 1 }} up</span>
                    </button>
                    <button type="submit" name="op" value="move_down:{{ $index }}" formnovalidate @disabled($index === count($steps) - 1)
                            class="inline-flex items-center rounded-md bg-white dark:bg-white/10 px-2 py-1.5 text-sm font-semibold text-stone-900 dark:text-white shadow-xs inset-ring inset-ring-stone-300 dark:inset-ring-white/5 hover:bg-stone-50 dark:hover:bg-white/20 disabled:opacity-40">
                        &darr;<span class="sr-only">Move step {{ $index + 1 }} down</span>
                    </button>
                    <button type="submit" name="op" value="remove_step:{{ $index }}" formnovalidate
                            class="text-stone-700 dark:text-stone-300 underline">
                        Remove<span class="sr-only"> step {{ $index + 1 }}</span>
                    </button>
                </div>
            @endforeach
        </div>

        @error('steps')
            <p class="mt-2 text-red-700 dark:text-red-400">{{ $message }}</p>
        @enderror

        <button type="submit" name="op" value="add_step" formnovalidate
                class="mt-3 inline-flex items-center gap-1.5 rounded-md bg-white dark:bg-white/10 px-3 py-1.5 text-sm font-semibold text-stone-900 dark:text-white shadow-xs inset-ring inset-ring-stone-300 dark:inset-ring-white/5 hover:bg-stone-50 dark:hover:bg-white/20">
            Add step
        </button>
    </div>

    <div class="mt-6 flex items-center gap-3">
        <button type="submit" name="op" value="save"
                class="inline-flex min-h-11 items-center rounded-md bg-stone-900 dark:bg-stone-100 px-4 text-sm font-semibold text-white dark:text-stone-900 shadow-xs hover:bg-stone-800 dark:hover:bg-stone-200">
            {{ $submitLabel }}
        </button>
        <a href="{{ route('admin.funnels.index') }}" class="text-stone-700 dark:text-stone-300 underline">Cancel</a>
    </div>
</form>
