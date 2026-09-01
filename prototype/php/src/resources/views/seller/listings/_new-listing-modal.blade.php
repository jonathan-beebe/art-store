{{--
    The "New listing" button's dialog (NewListingModal.dc.html): the same
    question screen /seller/listings/create shows on its own page, opened
    over the Listings list+detail screen instead of navigating away. A
    native <dialog>, opened and closed the same vanilla-JS way the seller
    chrome's off-canvas nav drawer already is
    (resources/views/components/layouts/seller.blade.php) — no dialog
    library. Continue is a plain GET submit, so it lands on the same
    shape-landing screen a direct visit to /seller/listings/create would.
--}}
<dialog id="new-listing-dialog" aria-labelledby="new-listing-title" class="fixed inset-0 z-50 m-0 h-dvh max-h-none w-full max-w-none items-center justify-center bg-transparent p-4 open:flex backdrop:bg-gray-500/75 dark:backdrop:bg-gray-900/70">
    <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10">
        <h2 id="new-listing-title" class="text-lg font-semibold text-gray-900 dark:text-white">New listing</h2>
        <p class="mt-1 text-gray-600 dark:text-gray-400">Two questions to start. Everything else — images, category, the listing page — waits on the editor, where each part guides you as you go.</p>

        <form method="GET" action="{{ route('seller.listings.create') }}" class="mt-5 flex flex-col gap-4">
            @include('seller.listings._create-form-fields')

            <div class="mt-2 flex justify-end gap-3">
                <button type="button" data-dialog-close class="rounded-md bg-white px-3 py-2 font-semibold text-gray-900 inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:inset-ring-white/5 dark:hover:bg-white/20">Cancel</button>
                <button type="submit" class="rounded-md bg-indigo-600 px-3 py-2 font-semibold text-white shadow-xs hover:bg-indigo-500">Continue</button>
            </div>
        </form>
    </div>
</dialog>

<script>
    (() => {
        const dialog = document.getElementById('new-listing-dialog');
        if (! dialog) return;

        document.querySelectorAll('[data-new-listing-open]').forEach((button) => {
            button.addEventListener('click', () => dialog.showModal());
        });

        dialog.querySelectorAll('[data-dialog-close]').forEach((button) => {
            button.addEventListener('click', () => dialog.close());
        });

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
    })();
</script>
