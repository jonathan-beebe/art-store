<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Logging\Admin\LogFilterInput;
use App\Logging\Admin\LogRowFilters;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * The `/admin/logs` query string (docs/spec.md §5): every filter
 * `LogFilterInput` names, plus the viewer's own three switches and its
 * page. A value outside the vocabulary answers 400: there is no form to
 * land back on for a redirect with errors.
 */
final class LogsQueryRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            ...LogFilterInput::rules(),
            'group' => ['nullable', 'in:1'],
            'health' => ['nullable', 'in:1'],
            'viewer' => ['nullable', 'in:1'],
            'mcp' => ['nullable', 'in:1'],
            'page' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(LogFilterInput::requireKeyForValue(...));
    }

    protected function prepareForValidation(): void
    {
        /** @var array<string, mixed> $given */
        $given = $this->only(array_keys($this->rules()));

        $this->merge(LogFilterInput::blanked($given));
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response('', 400));
    }

    public function filters(): LogRowFilters
    {
        /** @var array<string, mixed> $input */
        $input = $this->all();

        return LogFilterInput::filters(
            $input,
            hideHealth: $this->input('health') !== '1',
            hideViewer: $this->input('viewer') !== '1',
            hideMcp: $this->input('mcp') !== '1',
        );
    }

    public function grouped(): bool
    {
        return $this->input('group') === '1';
    }

    public function page(): ?string
    {
        return $this->stringOrNull('page');
    }

    /**
     * The filters as given, for building links that carry them forward.
     *
     * @return array<string, string>
     */
    public function roundTrippedFilters(): array
    {
        $fields = [...LogFilterInput::FIELDS, 'group', 'health', 'viewer', 'mcp'];
        $filters = [];

        foreach ($fields as $field) {
            $value = $this->stringOrNull($field);

            if ($value !== null) {
                $filters[$field] = $value;
            }
        }

        return $filters;
    }

    private function stringOrNull(string $field): ?string
    {
        $value = $this->input($field);

        return is_string($value) ? $value : null;
    }
}
