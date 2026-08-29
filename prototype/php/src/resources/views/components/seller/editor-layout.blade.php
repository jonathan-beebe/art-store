{{--
    IMPRV-015: the two-column shell every listing-editor screen (the hub,
    Basics, and the six configurator sections) wrapped by hand — the grid,
    the 380px right column, the left column's own vertical stack. Column
    widths had drifted to 380/400/420px and 24rem across screens before this
    collapsed them to one file; 380px is the ticket's own named target, wide
    enough for the buyer-view panel and no wider than it needs to be.
--}}
<div class="mt-4 grid grid-cols-1 items-start gap-6 lg:grid-cols-[1fr_380px]">
    <div class="flex flex-col gap-4">
        {{ $slot }}
    </div>

    <div class="flex flex-col gap-4">
        {{ $panel }}
    </div>
</div>
