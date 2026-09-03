<x-layouts.admin :title="'Edit '.$funnel->name.' — Art Store admin'">
    <p><a href="{{ route('admin.funnels.index') }}" class="text-stone-700 dark:text-stone-300 underline">&larr; Funnels</a></p>
    <h1 class="mt-2 text-xl font-semibold">{{ $funnel->name }}</h1>

    <x-admin.funnels.editor
        :action="route('admin.funnels.update', $funnel)"
        method="PUT"
        :name="$name"
        :steps="$steps"
        :eventNames="$eventNames"
        submitLabel="Save changes"
    />

    <form method="POST" action="{{ route('admin.funnels.destroy', $funnel) }}" class="mt-4 max-w-2xl">
        @csrf
        @method('DELETE')
        <button type="submit" class="text-red-700 dark:text-red-400 underline">Delete this funnel</button>
    </form>
</x-layouts.admin>
