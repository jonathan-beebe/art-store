<x-layouts.seller title="New listing — Art Store seller">
    <p><a href="{{ route('seller.listings.index') }}" class="text-gray-700 dark:text-gray-300 underline">&larr; Listings</a></p>
    <h1 class="mt-2 text-xl font-semibold">New listing</h1>
    <p class="mt-1 max-w-xl text-gray-600 dark:text-gray-400">Two questions to start. Everything else — images, category, the listing page — waits on the editor, where each part guides you as you go.</p>

    <form method="GET" action="{{ route('seller.listings.create') }}" class="mt-4 flex max-w-xl flex-col gap-4">
        @include('seller.listings._create-form-fields')

        <div>
            <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Continue</button>
        </div>
    </form>
</x-layouts.seller>
