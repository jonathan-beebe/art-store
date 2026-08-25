<x-layouts.seller title="New listing — Art Store seller">
    <h1 class="text-xl font-semibold">New listing</h1>

    <form method="POST" action="{{ route('seller.listings.store') }}" enctype="multipart/form-data"
          class="mt-4 max-w-3xl rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
        <fieldset>
            <legend class="font-semibold text-gray-700 dark:text-gray-300">Listing details</legend>

            @include('seller.listings.form', ['listing' => null])
        </fieldset>

        <div class="mt-6 flex items-center gap-3">
            <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Save as draft</button>
            <a href="{{ route('seller.listings.index') }}" class="text-gray-700 dark:text-gray-300 underline">Cancel</a>
        </div>
    </form>
</x-layouts.seller>
