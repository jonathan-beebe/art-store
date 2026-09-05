<x-layouts.admin :title="'Delete '.$funnel->name.' — Art Store admin'">
    <p><a href="{{ route('admin.funnels.edit', $funnel) }}" class="text-stone-700 dark:text-stone-300 underline">&larr; {{ $funnel->name }}</a></p>
    <h1 class="mt-2 text-xl font-semibold">Delete {{ $funnel->name }}?</h1>
    <p class="mt-2 text-stone-600 dark:text-stone-400">This removes the funnel and its tile from the analytics home. This cannot be undone.</p>

    <div class="mt-4 flex items-center gap-3">
        <form method="POST" action="{{ route('admin.funnels.destroy', $funnel) }}">
            @csrf
            @method('DELETE')
            <button type="submit" class="inline-flex min-h-11 items-center rounded-md bg-red-700 px-4 text-sm font-semibold text-white shadow-xs hover:bg-red-600">
                Delete funnel
            </button>
        </form>
        <a href="{{ route('admin.funnels.edit', $funnel) }}" class="text-stone-700 dark:text-stone-300 underline">Cancel</a>
    </div>
</x-layouts.admin>
