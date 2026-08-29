{{--
    Question/answer rows for a Q & A section.
    $rows: list<array{question: string, answer: string}>, already padded
    with blank rows for a JS-off "add a row".
    $idPrefix: makes each input's id unique when this partial is included
    more than once on the same page (one card per section).
--}}
<div>
    <span class="block font-medium text-gray-700 dark:text-gray-300">Questions</span>
    <div class="mt-1 flex flex-col gap-2">
        @foreach ($rows as $i => $row)
            <div class="flex flex-col gap-1 sm:flex-row sm:flex-wrap">
                <label for="faq-{{ $idPrefix }}-question-{{ $i }}" class="sr-only">Question</label>
                <input id="faq-{{ $idPrefix }}-question-{{ $i }}" type="text" name="faq[{{ $i }}][question]" value="{{ old("faq.$i.question", $row['question'] ?? '') }}" placeholder="Question" class="flex-1 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                <label for="faq-{{ $idPrefix }}-answer-{{ $i }}" class="sr-only">Answer</label>
                <input id="faq-{{ $idPrefix }}-answer-{{ $i }}" type="text" name="faq[{{ $i }}][answer]" value="{{ old("faq.$i.answer", $row['answer'] ?? '') }}" placeholder="Answer" class="flex-1 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
            </div>
        @endforeach
    </div>
</div>
