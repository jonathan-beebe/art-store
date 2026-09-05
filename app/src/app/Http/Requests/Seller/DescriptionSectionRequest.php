<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Configurator\ConfiguratorPublishValidation;
use App\Domain\Configurator\DescriptionSectionKind;
use App\Models\DescriptionSection;
use App\Models\Listing;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * The ≤15-section cap is enforced here, on a new section only, reading
 * {@see ConfiguratorPublishValidation::MAX_SECTIONS} directly, with no
 * repeated number. A row-editing kind (specs, a size chart, Q & A) arrives
 * as labeled rows — `spec[]`, `size_chart[]`, `faq[]` — so a seller never
 * hand-authors raw JSON; {@see self::completeRows()} folds whichever one
 * the section's kind uses into the list `body_json` stores.
 */
final class DescriptionSectionRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->listing());
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(DescriptionSectionKind::class)],
            'title' => ['nullable', 'string', 'max:255'],
            'body_md' => ['nullable', 'string'],
            'spec' => ['nullable', 'array'],
            'spec.*.label' => ['nullable', 'string', 'max:255'],
            'spec.*.value' => ['nullable', 'string', 'max:255'],
            'faq' => ['nullable', 'array'],
            'faq.*.question' => ['nullable', 'string', 'max:255'],
            'faq.*.answer' => ['nullable', 'string'],
            'size_chart' => ['nullable', 'array'],
            'size_chart.*.label' => ['nullable', 'string', 'max:255'],
            'size_chart.*.value1' => ['nullable', 'string', 'max:255'],
            'size_chart.*.value2' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $isNewSection = ! $this->route('description_section') instanceof DescriptionSection;

            if ($isNewSection && $this->listing()->descriptionSections()->count() >= ConfiguratorPublishValidation::MAX_SECTIONS) {
                $validator->errors()->add(
                    'kind',
                    'This listing already holds '.ConfiguratorPublishValidation::MAX_SECTIONS.' description sections, the most allowed.',
                );
            }
        });
    }

    public function kind(): DescriptionSectionKind
    {
        return $this->enum('kind', DescriptionSectionKind::class) ?? throw new RuntimeException('The kind rule admits only description section kinds.');
    }

    public function title(): ?string
    {
        return $this->filled('title') ? $this->string('title')->toString() : null;
    }

    public function bodyMd(): ?string
    {
        return $this->filled('body_md') ? $this->string('body_md')->toString() : null;
    }

    /**
     * @return list<array<string, string>>|null
     */
    public function bodyJson(): ?array
    {
        return match ($this->kind()) {
            DescriptionSectionKind::Specs => $this->completeRows('spec', ['label', 'value']),
            DescriptionSectionKind::Faq => $this->completeRows('faq', ['question', 'answer']),
            DescriptionSectionKind::SizeChart => $this->completeRows('size_chart', ['label', 'value1', 'value2']),
            DescriptionSectionKind::Text, DescriptionSectionKind::Care, DescriptionSectionKind::Disclaimer => null,
        };
    }

    public function listing(): Listing
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            ? $listing
            : throw new RuntimeException('The description section route binds a listing.');
    }

    /**
     * Zips one field's array-of-rows input into the list `body_json` stores,
     * keeping only rows where every named column is filled. The fixed
     * blank rows a JS-off "add a row" leaves behind when a seller ignores
     * them contribute nothing, and a half-filled row would break the shape
     * every renderer expects.
     *
     * @param  list<string>  $columns
     * @return list<array<string, string>>|null
     */
    private function completeRows(string $field, array $columns): ?array
    {
        $rows = [];

        foreach ($this->array($field) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $values = $this->rowValues($row, $columns);

            if (in_array('', $values, true)) {
                continue;
            }

            $rows[] = $values;
        }

        return $rows === [] ? null : $rows;
    }

    /**
     * @param  array<array-key, mixed>  $row
     * @param  list<string>  $columns
     * @return array<string, string>
     */
    private function rowValues(array $row, array $columns): array
    {
        $values = [];
        foreach ($columns as $column) {
            $values[$column] = is_string($row[$column] ?? null) ? trim($row[$column]) : '';
        }

        return $values;
    }
}
