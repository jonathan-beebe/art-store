<x-layouts.seller :title="'Edit listing — Art Store seller'">
    <h1 class="text-xl font-semibold">Edit {{ $listing->title }}</h1>

    <form method="POST" action="{{ route('seller.listings.update', $listing->id) }}" enctype="multipart/form-data"
          class="mt-4 max-w-3xl rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
        @method('PUT')

        <fieldset>
            <legend class="font-semibold text-gray-700 dark:text-gray-300">Listing details</legend>

            <img src="{{ $listing->imageUrl() }}" alt="Current image for {{ $listing->title }}" width="160" height="160"
                 class="mb-4 h-40 w-40 rounded border border-gray-300 dark:border-gray-700 object-cover">

            @include('seller.listings.form', ['listing' => $listing])
        </fieldset>

        <div class="mt-6 flex items-center gap-3">
            <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Save changes</button>
            <a href="{{ route('seller.listings.index') }}" class="text-gray-700 dark:text-gray-300 underline">Cancel</a>
        </div>
    </form>

    <section aria-labelledby="configurator-heading" class="mt-6 max-w-3xl rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
        <h2 id="configurator-heading" class="font-semibold text-gray-700 dark:text-gray-300">Configurator</h2>
        <p class="mt-1 text-gray-600 dark:text-gray-400">Axes, variants, units, modifiers, quantity breaks, and description sections — everything a buyer configures before adding this listing to cart.</p>

        <ul class="mt-3 flex flex-wrap gap-3">
            <li><a href="{{ route('seller.listings.option-axes.index', $listing) }}" class="rounded border border-gray-400 dark:border-gray-600 px-3 py-2">Axes &amp; options</a></li>
            <li><a href="{{ route('seller.listings.variants.index', $listing) }}" class="rounded border border-gray-400 dark:border-gray-600 px-3 py-2">Variants</a></li>
            <li><a href="{{ route('seller.listings.modifiers.index', $listing) }}" class="rounded border border-gray-400 dark:border-gray-600 px-3 py-2">Modifiers</a></li>
            <li><a href="{{ route('seller.listings.quantity-breaks.index', $listing) }}" class="rounded border border-gray-400 dark:border-gray-600 px-3 py-2">Quantity breaks</a></li>
            <li><a href="{{ route('seller.listings.description-sections.index', $listing) }}" class="rounded border border-gray-400 dark:border-gray-600 px-3 py-2">Description sections</a></li>
        </ul>

        @if (! empty($publishIssues))
            @php
                $issueUrl = function (\App\Domain\Configurator\PublishIssue $issue) use ($listing): string {
                    return match ($issue->code) {
                        'variant_priced_negative', 'variant_missing_axis_value' => route('seller.listings.variants.index', $listing).'#'.$issue->subjectId,
                        'serialized_variant_has_no_units' => route('seller.listings.variants.units.index', [$listing, $issue->subjectId]),
                        'axis_too_many_options' => route('seller.listings.option-axes.index', $listing),
                        'too_many_variants' => route('seller.listings.variants.index', $listing),
                        'too_many_modifiers' => route('seller.listings.modifiers.index', $listing),
                        'too_many_quantity_tiers' => route('seller.listings.quantity-breaks.index', $listing),
                        'too_many_sections' => route('seller.listings.description-sections.index', $listing),
                        'missing_required_attribute' => route('seller.listings.edit', $listing).'#attribute-'.$issue->subjectId,
                        default => route('seller.listings.edit', $listing),
                    };
                };
            @endphp

            <div role="alert" class="mt-4 rounded border border-red-300 dark:border-red-900 bg-red-50 dark:bg-red-950/40 p-4 text-red-900 dark:text-red-200">
                <p class="font-semibold">Not ready to publish — {{ count($publishIssues) }} issue(s):</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($publishIssues as $issue)
                        <li><a href="{{ $issueUrl($issue) }}" class="underline">{{ $issue->message }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    @if ($attributeGrants->isNotEmpty())
        <section aria-labelledby="attributes-heading" class="mt-6 max-w-3xl rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
            <h2 id="attributes-heading" class="font-semibold text-gray-700 dark:text-gray-300">Attributes</h2>
            <p class="mt-1 text-gray-600 dark:text-gray-400">Fixed facts about this listing, gated by its category — shown to buyers as Highlights on the storefront.</p>

            <form method="POST" action="{{ route('seller.listings.attributes.update', $listing) }}" class="mt-3 space-y-4">
                @csrf
                @method('PUT')

                @foreach ($attributeGrants as $grant)
                    @php $selected = $listingAttributeSelections[$grant->property_id] ?? []; @endphp
                    <div id="attribute-{{ $grant->property_id }}">
                        <label for="attribute-select-{{ $grant->property_id }}" class="block font-medium text-gray-700 dark:text-gray-300">
                            {{ $grant->property->name }}@if ($grant->required)<span class="text-red-700 dark:text-red-400"> *</span>@endif
                        </label>
                        <select id="attribute-select-{{ $grant->property_id }}" name="attribute[{{ $grant->property_id }}][]"
                                @if ($grant->multivalued) multiple @endif
                                class="mt-1 block w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                            @unless ($grant->multivalued)
                                <option value="">— None —</option>
                            @endunless
                            @foreach ($grant->property->values as $value)
                                <option value="{{ $value->id }}" @selected(in_array($value->id, $selected, true))>{{ $value->label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach

                <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Save attributes</button>
            </form>
        </section>
    @endif
</x-layouts.seller>
