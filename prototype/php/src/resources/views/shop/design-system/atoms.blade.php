<section id="atoms" class="mt-16 scroll-mt-8" aria-labelledby="atoms-heading">
    <h2 id="atoms-heading" class="font-display text-2xl text-ink">Atoms</h2>
    <p class="mt-2 max-w-2xl text-ink-faint">
        The <code class="font-mono text-xs">&lt;x-ui.*&gt;</code> components every shop view builds from —
        rendered here by the same Blade files the pages use.
    </p>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="rounded-card border border-line bg-surface p-6">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-ink-faint">Buttons</h3>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <x-ui.button type="button" variant="primary">Add to cart</x-ui.button>
                <x-ui.button type="button" variant="secondary">Favorite</x-ui.button>
                <x-ui.button type="button" variant="primary" disabled>Add to cart</x-ui.button>
                <x-ui.button type="button" variant="secondary" disabled>Favorite</x-ui.button>
            </div>
        </div>

        <div class="rounded-card border border-line bg-surface p-6">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-ink-faint">Chips &amp; badges</h3>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <x-ui.chip href="#atoms">Ceramics</x-ui.chip>
                <x-ui.chip href="#atoms" :active="true">Under $100</x-ui.chip>
                <x-ui.chip>Ships free</x-ui.chip>
                <x-ui.badge>One of a kind</x-ui.badge>
                <x-ui.badge>Made to order</x-ui.badge>
            </div>
        </div>

        <div class="rounded-card border border-line bg-surface p-6">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-ink-faint">Avatars</h3>
            <p class="mt-1 text-xs text-ink-faint">A maker's initial on a tint picked from their name — the same maker is the same color everywhere.</p>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <x-ui.avatar name="Molly Weasley" size="xs" />
                <x-ui.avatar name="Luna Lovegood" size="sm" />
                <x-ui.avatar name="Neville Longbottom" size="md" />
                <x-ui.avatar name="Sybill Trelawney" size="lg" />
                <x-ui.avatar name="Colin Creevey" size="lg" />
            </div>
        </div>

        <div class="rounded-card border border-line bg-surface p-6">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-ink-faint">Alerts</h3>
            <div class="mt-4 space-y-3">
                <x-ui.alert tone="danger"><p>That piece just sold — your cart has been updated.</p></x-ui.alert>
                <x-ui.alert tone="success"><p>Order placed. The maker has been notified.</p></x-ui.alert>
                <x-ui.alert tone="notice"><p>This order is awaiting payment.</p></x-ui.alert>
            </div>
        </div>

        <div class="rounded-card border border-line bg-surface p-6 lg:col-span-2">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-ink-faint">Fields</h3>
            <div class="mt-4 grid gap-6 sm:grid-cols-3">
                <div>
                    <x-ui.label for="ds-input">Your question</x-ui.label>
                    <x-ui.input id="ds-input" type="text" class="mt-2" placeholder="Ask about size, materials, shipping…" />
                </div>
                <div>
                    <x-ui.label for="ds-select">Medium</x-ui.label>
                    <x-ui.select id="ds-select" class="mt-2">
                        <option>All media</option>
                        <option>Ceramic</option>
                        <option>Oil</option>
                    </x-ui.select>
                </div>
                <div>
                    <x-ui.label for="ds-textarea">Message the maker</x-ui.label>
                    <x-ui.textarea id="ds-textarea" rows="2" class="mt-2" placeholder="Hi Molly —"></x-ui.textarea>
                </div>
            </div>
        </div>
    </div>
</section>
