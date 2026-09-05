<x-layouts.seller title="New workflow — Art Store seller">
    <p><a href="{{ route('seller.workflows.index') }}" class="text-gray-700 dark:text-gray-300 underline">&larr; Workflows</a></p>

    <h1 class="mt-2 text-xl font-semibold">New workflow</h1>
    <p class="mt-1 text-sm/6 text-gray-500 dark:text-gray-400">Name it and lay out its steps.</p>

    @include('seller.workflows._form', [
        'action' => route('seller.workflows.store'),
        'method' => 'POST',
        'name' => old('name', ''),
        'steps' => collect(),
        'submitLabel' => 'Add workflow',
    ])
</x-layouts.seller>
