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
                    class="rounded-full bg-accent px-8 py-3 text-base font-semibold text-on-accent hover:bg-accent-strong disabled:cursor-not-allowed disabled:bg-line disabled:text-ink-faint">
                Add to cart
            </button>
        </form>
    @else
        <button type="submit" @disabled($disabled)
                class="rounded-full bg-accent px-8 py-3 text-base font-semibold text-on-accent hover:bg-accent-strong disabled:cursor-not-allowed disabled:bg-line disabled:text-ink-faint">
            Add to cart
        </button>
    @endif
@else
    <span aria-disabled="true" class="inline-block rounded-full bg-line px-6 py-2 text-sm font-medium text-ink-faint">Add to cart</span>
@endif
