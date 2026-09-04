<x-layouts.seller :title="$workflow->name.' — Art Store seller'">
    <p><a href="{{ route('seller.workflows.index') }}" class="text-gray-700 dark:text-gray-300 underline">&larr; Workflows</a></p>

    <h1 class="mt-2 text-xl font-semibold">{{ $workflow->name }}</h1>
    <p class="mt-1 text-sm/6 text-gray-500 dark:text-gray-400">
        @if ($workflow->is_default)
            The default workflow. A listing that names none ships by this one.
        @else
            The steps a parcel goes through under this workflow.
        @endif
    </p>

    @include('seller.workflows._form', [
        'action' => route('seller.workflows.update', $workflow),
        'method' => 'PUT',
        'name' => old('name', $workflow->name),
        'steps' => $workflow->steps,
        'submitLabel' => 'Save workflow',
    ])
</x-layouts.seller>
