<x-layouts.seller :title="'Edit listing — Art Store seller'">
    <h1 class="text-xl font-semibold">Edit {{ $listing->title }}</h1>

    <form method="POST" action="{{ route('seller.listings.update', $listing->id) }}" enctype="multipart/form-data"
          class="mt-4 max-w-3xl rounded border border-gray-300 bg-white p-4">
        @method('PUT')

        <fieldset>
            <legend class="font-semibold text-gray-700">Listing details</legend>

            <img src="{{ $listing->imageUrl() }}" alt="Current image for {{ $listing->title }}" width="160" height="160"
                 class="mb-4 h-40 w-40 rounded border border-gray-300 object-cover">

            @include('seller.listings.form', ['listing' => $listing])
        </fieldset>

        <div class="mt-6 flex items-center gap-3">
            <button type="submit" class="rounded bg-gray-900 px-4 py-2 font-medium text-white">Save changes</button>
            <a href="{{ route('seller.listings.index') }}" class="text-gray-700 underline">Cancel</a>
        </div>
    </form>
</x-layouts.seller>
