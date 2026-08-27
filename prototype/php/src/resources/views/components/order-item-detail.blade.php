@props(['item'])

@if ($item->isConfigured())
    <dl class="text-sm text-gray-600 dark:text-gray-400">
        @foreach ($item->configuration_json ?? [] as $pair)
            <div><span class="font-medium">{{ $pair['axisName'] }}:</span> {{ $pair['optionValueLabel'] }}</div>
        @endforeach
        @foreach ($item->answers_json ?? [] as $answer)
            <div><span class="font-medium">{{ $answer['prompt'] }}:</span> {{ $answer['answer'] }}</div>
        @endforeach
    </dl>

    <ul class="mt-2 text-sm text-gray-500 dark:text-gray-500">
        @foreach ($item->priceBreakdown()->lines as $line)
            <li class="flex items-baseline justify-between gap-6">
                <span>{{ $line->label }}</span>
                <span class="tabular-nums">{{ $line->amount->format() }}</span>
            </li>
        @endforeach
    </ul>
@endif
