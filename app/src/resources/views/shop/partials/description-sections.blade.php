@php
    use App\Domain\Configurator\DescriptionSectionKind;
    use App\Support\Configurator\DescriptionSectionKindWord;

    // Two call sites share this markup with two different typographic
    // vocabularies: the seller's compact buyer-preview card, and the real
    // shop page's section headings. Callers override either slot; the
    // preview card's look is the default since it was the first caller.
    $sectionClass ??= 'mt-8 first:mt-0';
    $headingTag ??= 'p';
    $headingClass ??= 'text-base font-semibold';
@endphp

@foreach ($sections as $section)
    <section class="{{ $sectionClass }}">
        <{{ $headingTag }} class="{{ $headingClass }}">{{ $section->title ?? DescriptionSectionKindWord::forKind($section->kind) }}</{{ $headingTag }}>

        @if ($section->kind === DescriptionSectionKind::SizeChart)
            @if (($section->body_json ?? []) !== [])
                <div class="mt-2 overflow-x-auto">
                    <table class="w-full min-w-max border-collapse text-sm">
                        <tbody>
                            @foreach ($section->body_json as $row)
                                <tr class="border-b border-line">
                                    <td class="py-1 pr-6 font-medium">{{ $row['label'] ?? '' }}</td>
                                    <td class="py-1 pr-6 text-ink-muted">{{ $row['value1'] ?? '' }}</td>
                                    <td class="py-1 text-ink-muted">{{ $row['value2'] ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @elseif ($section->kind === DescriptionSectionKind::Specs)
            @if (($section->body_json ?? []) !== [])
                <dl class="mt-2 grid grid-cols-2 gap-y-1 text-sm">
                    @foreach ($section->body_json as $row)
                        <dt class="text-ink-faint">{{ $row['label'] ?? '' }}</dt>
                        <dd>{{ $row['value'] ?? '' }}</dd>
                    @endforeach
                </dl>
            @endif
        @elseif ($section->kind === DescriptionSectionKind::Faq)
            @if (($section->body_json ?? []) !== [])
                <dl class="mt-2 space-y-3 text-sm">
                    @foreach ($section->body_json as $row)
                        <div>
                            <dt class="font-medium">{{ $row['question'] ?? '' }}</dt>
                            <dd class="mt-1 text-ink-muted">{{ $row['answer'] ?? '' }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        @else
            <p class="mt-1 text-sm leading-relaxed text-ink-muted">{{ $section->body_md }}</p>
        @endif
    </section>
@endforeach
