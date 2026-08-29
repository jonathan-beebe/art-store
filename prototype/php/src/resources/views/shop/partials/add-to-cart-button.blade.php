@php
    // The one control IMPRV-015 keeps deliberately different between the
    // buyer page and the seller's buyer-view panel: a real submit that
    // places a cart line everywhere else, an inert span in a preview that
    // must never mutate a cart. `$standalone` renders its own POST form
    // (the unconfigured shop page and the panel's unconfigured branch,
    // neither already inside a configurator form); the configurator
    // partial passes `$standalone = false` since it supplies its own form.
    $standalone ??= true;
    $disabled ??= false;
@endphp

@if ($mode === 'shop')
    @if ($standalone)
        <form method="POST" action="{{ route('shop.cart.add', $listing) }}">
            @csrf
            <button type="submit" @disabled($disabled)
                    class="rounded-full bg-neutral-900 px-8 py-3 text-base font-medium text-white disabled:cursor-not-allowed disabled:bg-neutral-300">
                Add to cart
            </button>
        </form>
    @else
        <button type="submit" @disabled($disabled)
                class="rounded-full bg-neutral-900 px-8 py-3 text-base font-medium text-white disabled:cursor-not-allowed disabled:bg-neutral-300">
            Add to cart
        </button>
    @endif
@else
    <span aria-disabled="true" class="inline-block rounded-full bg-neutral-300 px-6 py-2 text-sm font-medium text-white">Add to cart</span>
@endif
