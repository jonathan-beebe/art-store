<x-layouts.seller title="Fulfillment flow — Art Store seller">
    @php
        $rows = $steps->values();
        $blankIndex = $rows->count();
    @endphp

    <x-seller.back-link :route="route('seller.orders.index')" label="Orders" />

    <h1 class="text-xl font-semibold">Fulfillment flow</h1>
    <p class="mt-1 text-sm/6 text-gray-500 dark:text-gray-400">
        The steps every parcel of yours goes through between paid and shipped. Every step you complete on an order is recorded on its activity feed.
    </p>

    <form method="POST" action="{{ route('seller.orders.flow.update') }}" class="mt-6 max-w-3xl">
        @csrf
        @method('PUT')

        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            <label for="flow-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Flow name</label>
            <input id="flow-name" name="name" type="text" required maxlength="80" value="{{ old('name', $name) }}"
                   class="mt-1 block w-full rounded-md bg-white px-3 py-1.5 text-sm text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10">
            @error('name')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <h2 class="mt-7 text-sm/6 font-semibold text-gray-900 dark:text-white">Steps</h2>
        <p class="mt-1 text-xs/5 text-gray-500 dark:text-gray-400">
            Order them with the numbers. Tick Remove to drop a step; a removed step leaves the orders that already completed it saying so.
        </p>

        <ul role="list" class="mt-2 divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white dark:divide-white/10 dark:border-white/10 dark:bg-gray-900">
            @foreach ($rows as $index => $step)
                <li class="flex flex-wrap items-end gap-4 p-4">
                    <input type="hidden" name="steps[{{ $index }}][id]" value="{{ $step->id }}">

                    <div class="w-20">
                        <label for="step-{{ $index }}-position" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Order</label>
                        <input id="step-{{ $index }}-position" name="steps[{{ $index }}][position]" type="number" min="1" max="99"
                               value="{{ old("steps.{$index}.position", $index + 1) }}"
                               class="mt-1 block w-full rounded-md bg-white px-3 py-1.5 text-sm text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10">
                    </div>

                    <div class="min-w-48 flex-1">
                        <label for="step-{{ $index }}-label" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Step</label>
                        <input id="step-{{ $index }}-label" name="steps[{{ $index }}][label]" type="text" maxlength="{{ \App\Domain\Fulfillment\FlowStepDraft::LABEL_LIMIT }}"
                               value="{{ old("steps.{$index}.label", $step->label) }}"
                               class="mt-1 block w-full rounded-md bg-white px-3 py-1.5 text-sm text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10">
                        @error("steps.{$index}.label")
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="w-56">
                        <label for="step-{{ $index }}-action" class="block text-sm font-medium text-gray-700 dark:text-gray-300">On completion</label>
                        <select id="step-{{ $index }}-action" name="steps[{{ $index }}][action]"
                                class="mt-1 block w-full rounded-md bg-white px-3 py-1.5 text-sm text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10">
                            @foreach (\App\Domain\Fulfillment\FlowStepAction::cases() as $action)
                                <option value="{{ $action->value }}" @selected(old("steps.{$index}.action", $step->action->value) === $action->value)>{{ $action->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <label class="flex min-h-11 items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="steps[{{ $index }}][remove]" value="1" class="size-4 rounded border-gray-300 text-indigo-600 dark:border-white/20 dark:bg-white/5">
                        Remove
                    </label>
                </li>
            @endforeach

            <li class="flex flex-wrap items-end gap-4 bg-gray-50 p-4 dark:bg-white/5">
                <div class="w-20">
                    <label for="step-new-position" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Order</label>
                    <input id="step-new-position" name="steps[{{ $blankIndex }}][position]" type="number" min="1" max="99"
                           value="{{ old("steps.{$blankIndex}.position", $blankIndex + 1) }}"
                           class="mt-1 block w-full rounded-md bg-white px-3 py-1.5 text-sm text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10">
                </div>

                <div class="min-w-48 flex-1">
                    <label for="step-new-label" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Add a step</label>
                    <input id="step-new-label" name="steps[{{ $blankIndex }}][label]" type="text" maxlength="{{ \App\Domain\Fulfillment\FlowStepDraft::LABEL_LIMIT }}"
                           value="{{ old("steps.{$blankIndex}.label") }}" placeholder="Kiln cooled"
                           class="mt-1 block w-full rounded-md bg-white px-3 py-1.5 text-sm text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">Leave it empty to add nothing.</p>
                </div>

                <div class="w-56">
                    <label for="step-new-action" class="block text-sm font-medium text-gray-700 dark:text-gray-300">On completion</label>
                    <select id="step-new-action" name="steps[{{ $blankIndex }}][action]"
                            class="mt-1 block w-full rounded-md bg-white px-3 py-1.5 text-sm text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10">
                        @foreach (\App\Domain\Fulfillment\FlowStepAction::cases() as $action)
                            <option value="{{ $action->value }}" @selected(old("steps.{$blankIndex}.action") === $action->value)>{{ $action->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </li>
        </ul>

        <div class="mt-6 flex items-center gap-3">
            <button type="submit" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">Save flow</button>
            <a href="{{ route('seller.orders.index') }}" class="text-sm/6 font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">Back to orders</a>
        </div>
    </form>
</x-layouts.seller>
