<x-layouts.seller-focused :listing="$listing" :title="'Images — '.$listing->title.' — Art Store seller'">
    <p><a href="{{ route('seller.listings.edit', $listing) }}" class="text-gray-700 dark:text-gray-300 underline">&larr; {{ $listing->title }}</a></p>
    <h1 class="mt-2 text-xl font-semibold">Images</h1>
    <p class="mt-1 max-w-xl text-gray-600 dark:text-gray-400">The first image is the one buyers see everywhere else on the site.</p>

    <div class="mt-4 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
        <div class="flex flex-wrap gap-4">
            @foreach ($images as $image)
                <div class="relative h-36 w-36 flex-shrink-0 overflow-hidden rounded border border-gray-300 dark:border-gray-700">
                    <img src="{{ $image->url() }}" alt="Image {{ $loop->iteration }} of {{ $listing->title }}" class="h-full w-full object-cover">

                    @if ($loop->first)
                        <span class="absolute left-2 top-2 rounded-full bg-gray-900 px-2 py-0.5 text-xs font-medium text-white">Cover</span>
                    @endif

                    <div class="absolute right-2 top-2 flex gap-1">
                        <form method="POST" action="{{ route('seller.listings.images.reorder', [$listing, $image]) }}">
                            @csrf
                            <input type="hidden" name="direction" value="up">
                            <button type="submit" class="inline-flex min-h-6 min-w-6 items-center justify-center rounded bg-gray-900/70 px-1.5 py-0.5 text-xs text-white" @disabled($loop->first)>&uarr;<span class="sr-only">Move up</span></button>
                        </form>
                        <form method="POST" action="{{ route('seller.listings.images.reorder', [$listing, $image]) }}">
                            @csrf
                            <input type="hidden" name="direction" value="down">
                            <button type="submit" class="inline-flex min-h-6 min-w-6 items-center justify-center rounded bg-gray-900/70 px-1.5 py-0.5 text-xs text-white" @disabled($loop->last)>&darr;<span class="sr-only">Move down</span></button>
                        </form>
                    </div>

                    <form method="POST" action="{{ route('seller.listings.images.destroy', [$listing, $image]) }}" class="absolute bottom-2 right-2">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex min-h-6 min-w-6 items-center justify-center rounded-full bg-gray-900/70 px-2 py-0.5 text-xs text-white">Remove</button>
                    </form>
                </div>
            @endforeach

            @if ($images->count() < $maxImages)
                <div class="flex h-36 w-36 flex-shrink-0 items-center justify-center rounded border border-dashed border-gray-400 dark:border-gray-600 p-2 text-center">
                    <form method="POST" action="{{ route('seller.listings.images.store', $listing) }}" enctype="multipart/form-data">
                        @csrf
                        <x-form.field name="image" label="Add an image" type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="text-xs" />
                        <button type="submit" class="mt-2 rounded bg-gray-900 dark:bg-gray-100 px-3 py-1.5 text-sm font-medium text-white dark:text-gray-900">Add</button>
                    </form>
                </div>
            @endif
        </div>

        @if ($images->count() >= $maxImages)
            <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">This listing already holds {{ $maxImages }} images, the most allowed.</p>
        @else
            <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">JPEG, PNG, WebP, or GIF up to 5 MB.</p>
        @endif

        <div class="mt-6">
            <a href="{{ route('seller.listings.edit', $listing) }}" class="text-gray-700 dark:text-gray-300 underline">Done</a>
        </div>
    </div>
</x-layouts.seller-focused>
