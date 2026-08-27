@php
    use App\Domain\Configurator\DescriptionSectionKind;
    use App\Support\Configurator\DescriptionSectionKindWord;
    use App\Support\Ordinal;
@endphp

<x-layouts.seller :title="'Listing page sections — '.$listing->title.' — Art Store seller'">
    <p><a href="{{ route('seller.listings.edit', $listing) }}" class="text-gray-700 dark:text-gray-300 underline">&larr; {{ $listing->title }}</a></p>
    <h1 class="mt-2 text-xl font-semibold">Listing page sections</h1>
    <p class="mt-1 max-w-2xl text-gray-600 dark:text-gray-400">Build the page in sections that render like a real product page — no ALL-CAPS headers, no size chart pasted as a wall of numbers.</p>

    <div class="mt-4 grid grid-cols-1 items-start gap-6 lg:grid-cols-[1fr_420px]">
        <div class="flex flex-col gap-4">
            @foreach ($sections as $section)
                <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                    <div class="flex flex-wrap items-baseline gap-2">
                        <p class="font-semibold text-gray-700 dark:text-gray-300">{{ $section->title ?? DescriptionSectionKindWord::forKind($section->kind) }}</p>
                        <span class="rounded-full border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-xs text-gray-700 dark:text-gray-300">{{ DescriptionSectionKindWord::forKind($section->kind) }}</span>
                        <span class="ml-auto text-gray-600 dark:text-gray-400">{{ Ordinal::of($loop->iteration) }}</span>

                        <form method="POST" action="{{ route('seller.listings.description-sections.reorder', [$listing, $section]) }}" class="contents">
                            @csrf
                            <input type="hidden" name="direction" value="up">
                            <button type="submit" class="rounded border border-gray-400 dark:border-gray-600 px-2 py-0.5 text-sm" @disabled($loop->first)>&uarr;<span class="sr-only">Move up</span></button>
                        </form>
                        <form method="POST" action="{{ route('seller.listings.description-sections.reorder', [$listing, $section]) }}" class="contents">
                            @csrf
                            <input type="hidden" name="direction" value="down">
                            <button type="submit" class="rounded border border-gray-400 dark:border-gray-600 px-2 py-0.5 text-sm" @disabled($loop->last)>&darr;<span class="sr-only">Move down</span></button>
                        </form>
                        <form method="POST" action="{{ route('seller.listings.description-sections.destroy', [$listing, $section]) }}" class="contents">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-gray-700 dark:text-gray-300 underline">Remove</button>
                        </form>
                    </div>

                    <form method="POST" action="{{ route('seller.listings.description-sections.update', [$listing, $section]) }}" class="mt-3 flex flex-col gap-3">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="kind" value="{{ $section->kind->value }}">

                        <x-form.field name="title" label="Title" maxlength="255" :value="$section->title" />

                        @if ($section->kind === DescriptionSectionKind::SizeChart)
                            @php $rows = array_merge($section->body_json ?? [], array_fill(0, 3, ['label' => '', 'value1' => '', 'value2' => ''])); @endphp
                            @include('seller.listings.description-sections._size-chart-rows', ['rows' => $rows, 'idPrefix' => $section->id])
                        @elseif ($section->kind === DescriptionSectionKind::Specs)
                            @php $rows = array_merge($section->body_json ?? [], array_fill(0, 3, ['label' => '', 'value' => ''])); @endphp
                            @include('seller.listings.description-sections._spec-rows', ['rows' => $rows, 'idPrefix' => $section->id])
                        @elseif ($section->kind === DescriptionSectionKind::Faq)
                            @php $rows = array_merge($section->body_json ?? [], array_fill(0, 3, ['question' => '', 'answer' => ''])); @endphp
                            @include('seller.listings.description-sections._faq-rows', ['rows' => $rows, 'idPrefix' => $section->id])
                        @else
                            <x-form.field name="body_md" label="What buyers read" type="textarea" class="w-full" rows="3" :value="$section->body_md" />
                        @endif

                        <div>
                            <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Save</button>
                        </div>
                    </form>

                    @if ($loop->first)
                        <p class="mt-3 text-gray-600 dark:text-gray-400">Leads the page today. Pinning it beside the buyer's choices is <span class="rounded-full border border-gray-200 dark:border-gray-700 px-2 py-0.5 text-xs">coming — not in this version</span>.</p>
                    @endif
                </div>
            @endforeach

            @if ($sections->isEmpty())
                <p class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 text-gray-600 dark:text-gray-400">No sections yet — add the first one below.</p>
            @endif

            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <p class="font-semibold text-gray-700 dark:text-gray-300">Add a section</p>

                @if ($addKind !== null)
                    <form method="POST" action="{{ route('seller.listings.description-sections.store', $listing) }}" class="mt-3 flex flex-col gap-3">
                        @csrf
                        <input type="hidden" name="kind" value="{{ $addKind->value }}">

                        <p class="text-gray-600 dark:text-gray-400">{{ DescriptionSectionKindWord::forKind($addKind) }}</p>

                        <x-form.field name="title" label="Title" maxlength="255" />

                        @if ($addKind === DescriptionSectionKind::SizeChart)
                            @include('seller.listings.description-sections._size-chart-rows', ['rows' => array_fill(0, 3, ['label' => '', 'value1' => '', 'value2' => '']), 'idPrefix' => 'new'])
                        @elseif ($addKind === DescriptionSectionKind::Specs)
                            @include('seller.listings.description-sections._spec-rows', ['rows' => array_fill(0, 3, ['label' => '', 'value' => '']), 'idPrefix' => 'new'])
                        @elseif ($addKind === DescriptionSectionKind::Faq)
                            @include('seller.listings.description-sections._faq-rows', ['rows' => array_fill(0, 3, ['question' => '', 'answer' => '']), 'idPrefix' => 'new'])
                        @else
                            <x-form.field name="body_md" label="What buyers read" type="textarea" class="w-full" rows="3" />
                        @endif

                        <div class="flex items-center gap-3">
                            <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Add the section</button>
                            <a href="{{ route('seller.listings.description-sections.index', $listing) }}" class="text-gray-700 dark:text-gray-300 underline">Choose a different type</a>
                        </div>
                    </form>
                @else
                    <div class="mt-3 flex flex-wrap gap-3">
                        @foreach (DescriptionSectionKind::cases() as $kind)
                            <a href="{{ route('seller.listings.description-sections.index', $listing) }}?kind={{ $kind->value }}"
                               class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-gray-900 dark:text-gray-100">
                                {{ DescriptionSectionKindWord::forKind($kind) }}
                                @if (DescriptionSectionKindWord::hint($kind) !== null)
                                    <span class="text-gray-600 dark:text-gray-400"> &middot; {{ DescriptionSectionKindWord::hint($kind) }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif

                <p class="mt-3 text-gray-600 dark:text-gray-400">Reusing one section across all your listings — the same disclaimer on 40 pages, edited once — is <span class="rounded-full border border-gray-200 dark:border-gray-700 px-2 py-0.5 text-xs">coming — not in this version</span>. Until then it's per listing.</p>
            </div>
        </div>

        <div class="relative rounded-lg border border-dashed border-neutral-400 bg-white p-5 text-neutral-900">
            <span class="absolute -top-3 left-4 rounded-full bg-neutral-800 px-3 py-0.5 text-xs font-medium text-white">What buyers see</span>
            <p class="mt-1 text-lg font-semibold text-neutral-900">{{ $listing->title }}</p>

            @if ($sections->isEmpty())
                <p class="mt-4 text-sm text-neutral-500">Nothing here yet — a section you add on the left shows up here exactly as buyers will read it.</p>
            @else
                @include('shop.partials.description-sections', ['sections' => $sections])
            @endif
        </div>
    </div>
</x-layouts.seller>
