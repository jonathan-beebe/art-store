<x-layouts.seller :title="'Questions & answers — '.$listing->title.' — Art Store seller'">
    <div class="flex flex-wrap items-center gap-4">
        <h1 class="text-xl font-semibold">Questions &amp; answers</h1>
        <a href="{{ route('seller.listings.show', $listing) }}" class="ml-auto text-gray-700 underline">Back to listing</a>
    </div>

    @if ($faqs->isEmpty())
        <p class="mt-4 rounded border border-gray-300 bg-white p-4 text-gray-600">Nothing published yet. Publish an answer from a message thread about this listing.</p>
    @else
        <ul class="mt-4 space-y-4">
            @foreach ($faqs as $faq)
                <li class="rounded border border-gray-300 bg-white p-4">
                    <form method="POST" action="{{ route('seller.listings.faqs.update', [$listing, $faq]) }}">
                        @csrf
                        @method('PUT')

                        <label for="question-{{ $faq->id }}" class="block font-medium text-gray-700">Question</label>
                        <input id="question-{{ $faq->id }}" name="question" type="text" required maxlength="500"
                               value="{{ old('question', $faq->question) }}"
                               class="mt-1 block w-full rounded border border-gray-400 px-3 py-2">

                        <label for="answer-{{ $faq->id }}" class="mt-4 block font-medium text-gray-700">Answer</label>
                        <textarea id="answer-{{ $faq->id }}" name="answer" required rows="4" maxlength="2000"
                                  class="mt-1 block w-full rounded border border-gray-400 px-3 py-2">{{ old('answer', $faq->answer) }}</textarea>

                        <button type="submit" class="mt-4 rounded bg-gray-900 px-4 py-2 font-medium text-white">Save</button>
                    </form>

                    <form method="POST" action="{{ route('seller.listings.faqs.destroy', [$listing, $faq]) }}" class="mt-2">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded border border-gray-400 px-3 py-2">Unpublish</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.seller>
