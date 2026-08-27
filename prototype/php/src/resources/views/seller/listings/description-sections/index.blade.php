<x-layouts.seller :title="'Description sections — '.$listing->title.' — Art Store seller'">
    <div class="flex flex-wrap items-center gap-4">
        <h1 class="text-xl font-semibold">Description sections</h1>
        <a href="{{ route('seller.listings.edit', $listing) }}" class="ml-auto text-gray-700 dark:text-gray-300 underline">Back to listing</a>
    </div>

    <p class="mt-2 text-gray-600 dark:text-gray-400">A typed section instead of one free-text field. Up to 15 sections.</p>

    @if ($sections->isEmpty())
        <p class="mt-4 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 text-gray-600 dark:text-gray-400">No sections yet.</p>
    @else
        <ul class="mt-4 space-y-4">
            @foreach ($sections as $section)
                <li class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                    <div class="flex items-center gap-2">
                        <form method="POST" action="{{ route('seller.listings.description-sections.reorder', [$listing, $section]) }}">
                            @csrf
                            <input type="hidden" name="direction" value="up">
                            <button type="submit" class="rounded border border-gray-400 dark:border-gray-600 px-2 py-1 text-sm" @disabled($loop->first)>Move up</button>
                        </form>
                        <form method="POST" action="{{ route('seller.listings.description-sections.reorder', [$listing, $section]) }}">
                            @csrf
                            <input type="hidden" name="direction" value="down">
                            <button type="submit" class="rounded border border-gray-400 dark:border-gray-600 px-2 py-1 text-sm" @disabled($loop->last)>Move down</button>
                        </form>
                        <span class="text-gray-600 dark:text-gray-400">Position {{ $section->position }}</span>
                    </div>

                    <form method="POST" action="{{ route('seller.listings.description-sections.update', [$listing, $section]) }}" class="mt-3 flex flex-wrap items-end gap-3">
                        @csrf
                        @method('PUT')

                        <div>
                            <label for="kind-{{ $section->id }}" class="block font-medium text-gray-700 dark:text-gray-300">Kind</label>
                            <select id="kind-{{ $section->id }}" name="kind" class="mt-1 block w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                                @foreach (\App\Domain\Configurator\DescriptionSectionKind::cases() as $kind)
                                    <option value="{{ $kind->value }}" @selected($section->kind === $kind)>{{ ucfirst(str_replace('_', ' ', $kind->value)) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <x-form.field name="title" label="Title" maxlength="255" :value="$section->title" />
                        <x-form.field name="body_md" label="Body (markdown)" type="textarea" class="w-full" rows="3" :value="$section->body_md" />
                        <x-form.field name="body_json" label="Body (JSON)" type="textarea" class="w-full" rows="3" :value="$section->body_json === null ? null : json_encode($section->body_json)" hint='For specs, size_chart, or faq — e.g. [{"label": "Height", "value": "10 in"}].' />

                        <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Save</button>
                    </form>

                    <form method="POST" action="{{ route('seller.listings.description-sections.destroy', [$listing, $section]) }}" class="mt-2">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded border border-gray-400 dark:border-gray-600 px-3 py-1 text-sm">Remove section</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif

    <h2 class="mt-6 font-semibold text-gray-700 dark:text-gray-300">Add a section</h2>

    <form method="POST" action="{{ route('seller.listings.description-sections.store', $listing) }}" class="mt-2 flex flex-wrap items-end gap-3 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
        @csrf

        <div>
            <label for="new-kind" class="block font-medium text-gray-700 dark:text-gray-300">Kind</label>
            <select id="new-kind" name="kind" class="mt-1 block w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                @foreach (\App\Domain\Configurator\DescriptionSectionKind::cases() as $kind)
                    <option value="{{ $kind->value }}">{{ ucfirst(str_replace('_', ' ', $kind->value)) }}</option>
                @endforeach
            </select>
        </div>

        <x-form.field name="title" label="Title" maxlength="255" />
        <x-form.field name="body_md" label="Body (markdown)" type="textarea" class="w-full" rows="3" />
        <x-form.field name="body_json" label="Body (JSON)" type="textarea" class="w-full" rows="3" hint='For specs, size_chart, or faq.' />

        <button type="submit" class="rounded border border-gray-400 dark:border-gray-600 px-4 py-2">Add section</button>
    </form>
</x-layouts.seller>
