<x-layouts.seller title="New listing — Art Store seller">
    <p><a href="{{ route('seller.listings.index') }}" class="text-gray-700 dark:text-gray-300 underline">&larr; Listings</a></p>
    <h1 class="mt-2 text-xl font-semibold">New listing</h1>
    <p class="mt-1 max-w-xl text-gray-600 dark:text-gray-400">Two questions to start. Everything else — images, category, the listing page — waits on the editor, where each part guides you as you go.</p>

    <form method="GET" action="{{ route('seller.listings.create') }}" class="mt-4 flex max-w-xl flex-col gap-4">
        <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
            <x-form.field name="title" label="What are you selling?" required maxlength="255"
                          placeholder="Name it the way a buyer would search for it" />
        </div>

        <fieldset>
            <legend class="mb-2 font-medium text-gray-700 dark:text-gray-300">How do you price it?</legend>

            <div class="flex flex-col gap-3">
                <label class="flex cursor-pointer gap-3 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 has-[:checked]:border-gray-900 dark:has-[:checked]:border-gray-100">
                    <input type="radio" name="shape" value="one" class="mt-1" required>
                    <span>
                        <span class="block font-semibold">One thing, one price</span>
                        <span class="mt-0.5 block text-gray-600 dark:text-gray-400">You sell it as it is — one price, and how many you have.</span>
                        <span class="mt-1.5 block text-sm text-gray-500 dark:text-gray-500">A finished painting · a print run at one size</span>
                    </span>
                </label>

                <label class="flex cursor-pointer gap-3 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 has-[:checked]:border-gray-900 dark:has-[:checked]:border-gray-100">
                    <input type="radio" name="shape" value="versions" class="mt-1" required>
                    <span>
                        <span class="block font-semibold">It comes in versions, each with its own price</span>
                        <span class="mt-0.5 block text-gray-600 dark:text-gray-400">Small, medium, large — every version just has a price. Nothing is a &ldquo;base.&rdquo;</span>
                        <span class="mt-1.5 block text-sm text-gray-500 dark:text-gray-500">A poster in three sizes · a ring in three metals</span>
                    </span>
                </label>

                <label class="flex cursor-pointer gap-3 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 has-[:checked]:border-gray-900 dark:has-[:checked]:border-gray-100">
                    <input type="radio" name="shape" value="extras" class="mt-1" required>
                    <span>
                        <span class="block font-semibold">One price, with extras that add to it</span>
                        <span class="mt-0.5 block text-gray-600 dark:text-gray-400">The item has a price; a frame, an engraving, or a nicer material adds to it.</span>
                        <span class="mt-1.5 block text-sm text-gray-500 dark:text-gray-500">A print with optional framing · a board with optional engraving</span>
                    </span>
                </label>
            </div>

            <p class="mt-2.5 text-sm text-gray-600 dark:text-gray-400">This just picks your starting point — you can add the other kinds later. A poster priced by size can still add a framing extra afterwards.</p>
        </fieldset>

        <div>
            <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Continue</button>
        </div>
    </form>
</x-layouts.seller>
