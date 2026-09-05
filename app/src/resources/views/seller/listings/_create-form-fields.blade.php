{{--
    The two questions a new listing starts with — the title field and the
    three pricing-shape cards — shared verbatim by the standalone
    /seller/listings/create page and the New listing modal
    (NewListingModal.dc.html) so the two can never drift. The caller
    supplies the enclosing <form> and its own submit control. Selected-card
    styling is an inset 2px indigo ring, per the artboard.
--}}
<div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
    <x-form.field name="title" label="What are you selling?" required maxlength="255"
                  placeholder="Name it the way a buyer would search for it" />
</div>

<fieldset>
    <legend class="mb-2 font-medium text-gray-700 dark:text-gray-300">How do you price it?</legend>

    <div class="flex flex-col gap-3">
        <label class="flex cursor-pointer gap-3 rounded-lg border border-gray-300 dark:border-white/10 bg-white dark:bg-gray-800/50 p-4 has-checked:outline-2 has-checked:-outline-offset-2 has-checked:outline-indigo-600 dark:has-checked:outline-indigo-500">
            <input type="radio" name="shape" value="one" class="mt-1 size-4 border-gray-300 text-indigo-600 focus:ring-indigo-600 dark:border-white/20 dark:bg-white/5" required>
            <span>
                <span class="block font-semibold text-gray-900 dark:text-white">One thing, one price</span>
                <span class="mt-0.5 block text-gray-600 dark:text-gray-400">You sell it as it is — one price, and how many you have.</span>
                <span class="mt-1.5 block text-sm text-gray-500 dark:text-gray-500">A finished painting · a print run at one size</span>
            </span>
        </label>

        <label class="flex cursor-pointer gap-3 rounded-lg border border-gray-300 dark:border-white/10 bg-white dark:bg-gray-800/50 p-4 has-checked:outline-2 has-checked:-outline-offset-2 has-checked:outline-indigo-600 dark:has-checked:outline-indigo-500">
            <input type="radio" name="shape" value="versions" class="mt-1 size-4 border-gray-300 text-indigo-600 focus:ring-indigo-600 dark:border-white/20 dark:bg-white/5" required>
            <span>
                <span class="block font-semibold text-gray-900 dark:text-white">It comes in versions, each with its own price</span>
                <span class="mt-0.5 block text-gray-600 dark:text-gray-400">Small, medium, large — every version just has a price. Nothing is a &ldquo;base.&rdquo;</span>
                <span class="mt-1.5 block text-sm text-gray-500 dark:text-gray-500">A poster in three sizes · a ring in three metals</span>
            </span>
        </label>

        <label class="flex cursor-pointer gap-3 rounded-lg border border-gray-300 dark:border-white/10 bg-white dark:bg-gray-800/50 p-4 has-checked:outline-2 has-checked:-outline-offset-2 has-checked:outline-indigo-600 dark:has-checked:outline-indigo-500">
            <input type="radio" name="shape" value="extras" class="mt-1 size-4 border-gray-300 text-indigo-600 focus:ring-indigo-600 dark:border-white/20 dark:bg-white/5" required>
            <span>
                <span class="block font-semibold text-gray-900 dark:text-white">One price, with extras that add to it</span>
                <span class="mt-0.5 block text-gray-600 dark:text-gray-400">The item has a price; a frame, an engraving, or a nicer material adds to it.</span>
                <span class="mt-1.5 block text-sm text-gray-500 dark:text-gray-500">A print with optional framing · a board with optional engraving</span>
            </span>
        </label>
    </div>

    <p class="mt-2.5 text-sm text-gray-600 dark:text-gray-400">This just picks your starting point — you can add the other kinds later. A poster priced by size can still add a framing extra afterwards.</p>
</fieldset>
