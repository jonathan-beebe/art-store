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
 * {@see ConfiguratorPublishValidation::MAX_SECTIONS} rather than repeating it.
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
            'body_json' => ['nullable', 'json'],
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
     * @return array<int|string, mixed>|null
     */
    public function bodyJson(): ?array
    {
        if (! $this->filled('body_json')) {
            return null;
        }

        $decoded = json_decode($this->string('body_json')->toString(), true);

        return is_array($decoded) ? $decoded : null;
    }

    public function listing(): Listing
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            ? $listing
            : throw new RuntimeException('The description section route binds a listing.');
    }
}
