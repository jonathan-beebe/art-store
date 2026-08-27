@php
    use App\Domain\Listings\ListingStatus;
    use App\Support\Configurator\PublishIssuePresenter;
@endphp

<x-layouts.seller :title="$listing->title.' — Art Store seller'">
    <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-xl font-semibold">{{ $listing->title }}</h1>
        <span class="rounded-full border border-gray-300 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 px-3 py-0.5 text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ $listing->status->label() }}
        </span>
    </div>

    <div class="mt-4 flex max-w-3xl flex-col gap-4">

        <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
            <p class="font-semibold text-gray-700 dark:text-gray-300">Your item</p>

            <form method="POST" action="{{ route('seller.listings.update', $listing->id) }}" enctype="multipart/form-data" class="mt-3">
                @method('PUT')

                <div class="flex gap-4">
                    <img src="{{ $listing->imageUrl() }}" alt="Current image for {{ $listing->title }}" width="160" height="160"
                         class="h-40 w-40 flex-shrink-0 rounded border border-gray-300 dark:border-gray-700 object-cover">

                    <div class="flex-1">
                        @include('seller.listings.form', ['listing' => $listing])
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-3">
                    <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Save</button>
                    <a href="{{ route('seller.listings.index') }}" class="text-gray-700 dark:text-gray-300 underline">Cancel</a>
                </div>
            </form>

            @if ($attributeGrants->isNotEmpty())
                <form method="POST" action="{{ route('seller.listings.attributes.update', $listing) }}" class="mt-4 space-y-4 border-t border-gray-200 dark:border-gray-800 pt-4">
                    @csrf
                    @method('PUT')

                    @foreach ($attributeGrants as $grant)
                        @php
                            $selected = $listingAttributeSelections[$grant->property_id] ?? [];
                            $isMedium = $grant->property->name === 'Medium';
                        @endphp
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
                            @if ($isMedium)
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Not on the list? Custom values aren't available yet — say it in the description.</p>
                            @endif
                        </div>
                    @endforeach

                    <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Save facts</button>
                </form>
            @endif
        </div>

        @if ($listing->status === ListingStatus::Draft && $publishIssues !== [])
            <div role="alert" class="rounded border border-red-300 dark:border-red-900 bg-red-50 dark:bg-red-950/40 p-4 text-red-900 dark:text-red-200">
                <p class="font-semibold">Before this can go live — {{ count($publishIssues) }} {{ count($publishIssues) === 1 ? 'thing' : 'things' }}:</p>
                <ul class="mt-2 flex flex-col gap-2">
                    @foreach ($publishIssues as $issue)
                        @php $presented = PublishIssuePresenter::present($issue, $listing); @endphp
                        <li class="flex flex-wrap items-baseline gap-3">
                            <span class="flex-1">{{ $presented->message }}</span>
                            <a href="{{ $presented->fixUrl }}" class="whitespace-nowrap underline">{{ $presented->fixLabel }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @elseif ($listing->status === ListingStatus::Draft)
            <div class="flex items-center gap-3 rounded border border-green-300 dark:border-green-900 bg-green-50 dark:bg-green-950/40 p-4 text-green-900 dark:text-green-200">
                <span class="font-semibold">Ready to go live — nothing is missing.</span>
                <form method="POST" action="{{ route('seller.listings.status', $listing) }}" class="ml-auto">
                    @csrf
                    <input type="hidden" name="status" value="{{ ListingStatus::ForSale->value }}">
                    <button type="submit" class="rounded bg-green-900 px-4 py-2 font-medium text-white">Put it up for sale</button>
                </form>
            </div>
        @endif

        @if ($choicesSummary === null)
            <div class="flex items-baseline gap-3 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <span class="font-medium">Comes in more than one version?</span>
                <span class="text-gray-600 dark:text-gray-400">Sizes, colors, materials — each can carry its own price.</span>
                <a href="{{ route('seller.listings.option-axes.index', $listing) }}" class="ml-auto whitespace-nowrap underline">Offer choices</a>
            </div>
        @else
            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <div class="flex items-baseline gap-2">
                    <p class="font-semibold text-gray-700 dark:text-gray-300">Choices you offer</p>
                    <a href="{{ route('seller.listings.option-axes.index', $listing) }}" class="ml-auto underline">Edit</a>
                </div>
                <div class="mt-2 flex flex-col gap-1">
                    @foreach ($choicesSummary['axes'] as $axis)
                        <div class="flex items-baseline gap-2">
                            <span class="w-24 flex-shrink-0 font-medium">{{ $axis['name'] }}</span>
                            <span>
                                {{ implode(' · ', $axis['displayedLabels']) }}@if ($axis['moreCount'] > 0) · {{ $axis['moreCount'] }} more @endif
                                @foreach ($axis['priceDeltas'] as $delta)
                                    <span class="rounded-full border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-xs">{{ $delta }}</span>
                                @endforeach
                            </span>
                        </div>
                    @endforeach
                </div>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ $choicesSummary['offeredCount'] }} of {{ $choicesSummary['totalCombinations'] }} combinations offered
                    @if ($choicesSummary['lowStockCount'] > 0) · {{ $choicesSummary['lowStockCount'] }} low on stock @endif
                    · <a href="{{ $choicesSummary['combinationsUrl'] }}" class="underline">Combinations &amp; stock</a>
                </p>
            </div>
        @endif

        @if ($questionsSummary === null)
            <div class="flex items-baseline gap-3 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <span class="font-medium">Need an answer from the buyer?</span>
                <span class="text-gray-600 dark:text-gray-400">A name to engrave, a pick from your list, a measurement.</span>
                <a href="{{ route('seller.listings.modifiers.index', $listing) }}" class="ml-auto whitespace-nowrap underline">Ask a question</a>
            </div>
        @else
            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <div class="flex items-baseline gap-2">
                    <p class="font-semibold text-gray-700 dark:text-gray-300">Questions you ask the buyer</p>
                    <a href="{{ route('seller.listings.modifiers.index', $listing) }}" class="ml-auto underline">Edit</a>
                </div>
                <div class="mt-2 flex flex-col gap-1">
                    @foreach ($questionsSummary as $question)
                        <div class="flex flex-wrap items-baseline gap-2">
                            <span>&ldquo;{{ $question['prompt'] }}&rdquo;</span>
                            @if ($question['priceLabel'] !== null)
                                <span class="rounded-full border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-xs">{{ $question['priceLabel'] }}</span>
                            @endif
                            @if ($question['required'])
                                <span class="rounded-full border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-xs">must answer</span>
                            @endif
                            @if ($question['scopeNote'] !== null)
                                <span class="text-gray-600 dark:text-gray-400">{{ $question['scopeNote'] }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($piecesSummary !== null)
            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <div class="flex items-baseline gap-2">
                    <p class="font-semibold text-gray-700 dark:text-gray-300">Individual pieces</p>
                    <a href="{{ $piecesSummary['url'] }}" class="ml-auto underline">Edit</a>
                </div>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ $piecesSummary['total'] }} pieces · {{ $piecesSummary['available'] }} available · {{ $piecesSummary['sold'] }} sold
                </p>
            </div>
        @endif

        @if ($discountsLine === null)
            <div class="flex items-baseline gap-3 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <span class="font-medium">Cheaper in bulk?</span>
                <span class="text-gray-600 dark:text-gray-400">Bigger orders get a lower per-item price.</span>
                <a href="{{ route('seller.listings.quantity-breaks.index', $listing) }}" class="ml-auto whitespace-nowrap underline">Add a discount</a>
            </div>
        @else
            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <div class="flex items-baseline gap-2">
                    <p class="font-semibold text-gray-700 dark:text-gray-300">Quantity discounts</p>
                    <a href="{{ route('seller.listings.quantity-breaks.index', $listing) }}" class="ml-auto underline">Edit</a>
                </div>
                <p class="mt-2">{{ $discountsLine }}</p>
            </div>
        @endif

        @if ($sectionsLine === null)
            <div class="flex items-baseline gap-3 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <span class="font-medium">More to say?</span>
                <span class="text-gray-600 dark:text-gray-400">Give the page real sections — a size chart, care notes, Q &amp; A.</span>
                <a href="{{ route('seller.listings.description-sections.index', $listing) }}" class="ml-auto whitespace-nowrap underline">Lay out the page</a>
            </div>
        @else
            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <div class="flex items-baseline gap-2">
                    <p class="font-semibold text-gray-700 dark:text-gray-300">Listing page sections</p>
                    <a href="{{ route('seller.listings.description-sections.index', $listing) }}" class="ml-auto underline">Edit</a>
                </div>
                <p class="mt-2">{{ $sectionsLine }}</p>
            </div>
        @endif

        @if ($piecesSummary === null)
            <div class="flex items-baseline gap-3 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <span class="font-medium">Every piece one of a kind?</span>
                <span class="text-gray-600 dark:text-gray-400">List each piece with its own price and condition.</span>
                <a href="{{ route('seller.listings.variants.index', $listing) }}" class="ml-auto whitespace-nowrap underline">List pieces</a>
            </div>
        @endif

        @if ($listing->status !== ListingStatus::Draft)
            <p class="text-gray-600 dark:text-gray-400">Editing a live listing never changes an order that's already placed — every order keeps the exact price and choices its buyer agreed to.</p>
        @endif

        <p class="text-sm text-gray-600 dark:text-gray-400">Art Store sells physical goods — digital downloads and file delivery aren't supported yet.</p>
    </div>
</x-layouts.seller>
