<x-layouts.seller title="Workflows — Art Store seller">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">Workflows</h1>
            <p class="mt-0.5 text-gray-500 dark:text-gray-400">The ways your goods ship. A listing that names none ships by the default.</p>
        </div>

        <a href="{{ route('seller.workflows.create') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">Add a workflow</a>
    </div>

    <div class="mt-5 overflow-x-auto rounded border border-gray-300 bg-white dark:border-gray-700 dark:bg-gray-900">
        <table class="w-full text-left">
            <caption class="sr-only">Every workflow, its step count, and the listings that name it</caption>
            <thead class="border-b border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50">
                <tr>
                    <th class="px-4 py-2 font-semibold">Workflow</th>
                    <th class="px-4 py-2 text-right font-semibold">Steps</th>
                    <th class="px-4 py-2 font-semibold">Listings</th>
                    <th class="px-4 py-2 font-semibold"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                @forelse ($flows as $flow)
                    <tr>
                        <td class="px-4 py-2">
                            <a href="{{ route('seller.workflows.edit', $flow) }}" class="font-semibold text-gray-900 hover:text-indigo-600 dark:text-gray-100 dark:hover:text-indigo-400">{{ $flow->name }}</a>
                            @if ($flow->is_default)
                                <span class="ml-2 inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">Default</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $flow->steps_count }}</td>
                        <td class="px-4 py-2 text-gray-600 dark:text-gray-400">
                            {{ $flow->listings->isEmpty() ? 'No listings' : $flow->listings->pluck('title')->implode(', ') }}
                        </td>
                        <td class="px-4 py-2 text-right">
                            @unless ($flow->is_default)
                                <div class="flex items-center justify-end gap-3">
                                    <form method="POST" action="{{ route('seller.workflows.default', $flow) }}">
                                        @csrf
                                        <button type="submit" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">Make default</button>
                                    </form>
                                    <form method="POST" action="{{ route('seller.workflows.destroy', $flow) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm font-semibold text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300">Remove</button>
                                    </form>
                                </div>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                            No workflows yet. <a href="{{ route('seller.workflows.create') }}" class="font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">Add one</a> — a listing that names none ships by the default.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.seller>
