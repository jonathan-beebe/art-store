<x-layouts.admin title="Funnels — Art Store admin">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold">Funnels</h1>
        <a href="{{ route('admin.funnels.create') }}"
           class="inline-flex min-h-11 items-center rounded-md bg-stone-900 dark:bg-stone-100 px-4 text-sm font-semibold text-white dark:text-stone-900 shadow-xs hover:bg-stone-800 dark:hover:bg-stone-200">
            New funnel
        </a>
    </div>

    <div class="mt-2 overflow-x-auto rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900">
        <table class="w-full text-left">
            <caption class="sr-only">Every funnel, in tile order</caption>
            <thead class="border-b border-stone-300 dark:border-stone-700 bg-stone-50 dark:bg-stone-800/50">
                <tr>
                    <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap">Name</th>
                    <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap">Steps</th>
                    <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
                @forelse ($funnels as $funnel)
                    <tr>
                        <td class="px-4 py-2 font-normal">
                            <a href="{{ route('admin.analytics.funnels.show', $funnel) }}" class="underline">{{ $funnel->name }}</a>
                        </td>
                        <td class="px-4 py-2 text-stone-600 dark:text-stone-400">{{ $stepChains[$funnel->id] }}</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            <a href="{{ route('admin.funnels.edit', $funnel) }}" class="text-stone-700 dark:text-stone-300 underline">Edit</a>
                            <form method="POST" action="{{ route('admin.funnels.destroy', $funnel) }}" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ml-3 text-stone-700 dark:text-stone-300 underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-2 text-stone-600 dark:text-stone-400">No funnels yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
