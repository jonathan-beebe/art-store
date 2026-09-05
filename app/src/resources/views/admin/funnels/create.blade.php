<x-layouts.admin title="New funnel — Art Store admin">
    <p><a href="{{ route('admin.funnels.index') }}" class="text-stone-700 dark:text-stone-300 underline">&larr; Funnels</a></p>
    <h1 class="mt-2 text-xl font-semibold">New funnel</h1>

    <x-admin.funnels.editor
        :action="route('admin.funnels.store')"
        method="POST"
        :name="$name"
        :steps="$steps"
        :eventNames="$eventNames"
        submitLabel="Create funnel"
    />
</x-layouts.admin>
