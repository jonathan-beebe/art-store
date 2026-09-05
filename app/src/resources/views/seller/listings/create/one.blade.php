<x-layouts.seller :title="$title.' — Art Store seller'">
    <p><a href="{{ route('seller.listings.create') }}" class="text-gray-700 dark:text-gray-300 underline">&larr; New listing</a></p>
    <h1 class="mt-2 text-xl font-semibold">{{ $title }}</h1>
    <p class="mt-1 max-w-lg text-gray-600 dark:text-gray-400">One thing, one price — two fields and it&rsquo;s a draft. Add images and the rest on the next screen.</p>

    <form method="POST" action="{{ route('seller.listings.store') }}"
          class="mt-4 max-w-md rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
        @csrf
        <input type="hidden" name="shape" value="one">
        <input type="hidden" name="title" value="{{ $title }}">

        <div class="grid grid-cols-2 gap-4">
            <x-form.field name="price" label="Price" type="number" step="0.01" min="0" required :value="old('price')" />

            <div>
                <x-form.field name="quantity" label="How many you have" type="number" step="1" min="0" max="999" :value="old('quantity')" />
                <label class="mt-1 flex items-center gap-2 text-gray-700 dark:text-gray-300">
                    <input type="checkbox" name="made_to_order" value="1" @checked(old('made_to_order'))>
                    Made to order — no fixed count
                </label>
            </div>
        </div>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">With &ldquo;Made to order&rdquo; checked, the count is ignored and the listing stays available.</p>

        <div class="mt-4">
            <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Create the draft</button>
        </div>
    </form>

    <p class="mt-4 max-w-lg text-sm text-gray-600 dark:text-gray-400">Creates the draft and lands on the editor: images, where buyers find it, the listing page, and &ldquo;before this can go live&rdquo; all wait there.</p>
</x-layouts.seller>
