<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Store\StoreSectionField;
use App\Domain\Store\StoreSectionKind;
use App\Models\Seller;
use App\Models\StoreProfile;
use App\Models\StoreSection;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use RuntimeException;
use Stringable;

/**
 * One block of a store page, added or edited. The kind decides which fields
 * carry meaning: {@see StoreSectionKind::allows()} is read for both halves
 * of that — the rules ask only for the fields the kind uses, and the
 * after-validation pass refuses a field it does not.
 *
 * A section already on the page keeps its kind; only a new one names one.
 */
final class StoreSectionRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->storeProfile());
    }

    /**
     * @return array<string, list<string|Stringable>>
     */
    public function rules(): array
    {
        $rules = [];

        if ($this->section() === null) {
            $rules['kind'] = ['required', Rule::enum(StoreSectionKind::class)];
        }

        if ($this->allows(StoreSectionField::Heading)) {
            $rules['heading'] = ['nullable', 'string', 'max:255'];
        }

        if ($this->allows(StoreSectionField::Body)) {
            $rules['body'] = ['nullable', 'string', 'max:'.StoreSection::MAX_BODY_LENGTH];
        }

        if ($this->allows(StoreSectionField::Images)) {
            $rules['images'] = ['array', 'max:'.StoreSection::MAX_GALLERY_IMAGES];
            $rules['images.*'] = [
                'string',
                'distinct',
                Rule::exists('store_images', 'id')->where('store_profile_id', $this->storeProfile()->id),
            ];
        }

        return $rules;
    }

    /**
     * The fields a kind does not use are refused rather than ignored, so a
     * form that sends a body to a gallery hears about it.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $kind = $this->kind();

            foreach (StoreSectionField::cases() as $field) {
                if (! $kind->allows($field) && $this->has($field->value)) {
                    $validator->errors()->add(
                        $field->value,
                        'A '.strtolower($kind->label()).' section has no '.$field->value.'.',
                    );
                }
            }

            if ($this->section() === null && $this->storeProfile()->sections()->count() >= StoreSection::MAX_PER_PROFILE) {
                $validator->errors()->add(
                    'kind',
                    'This store page already holds '.StoreSection::MAX_PER_PROFILE.' sections, the most allowed.',
                );
            }
        });
    }

    public function kind(): StoreSectionKind
    {
        $section = $this->section();

        return $section instanceof StoreSection ? $section->kind : $this->requestedKind();
    }

    public function heading(): ?string
    {
        $heading = $this->input('heading');

        return is_string($heading) && trim($heading) !== '' ? trim($heading) : null;
    }

    public function body(): ?string
    {
        $body = $this->input('body');

        return is_string($body) && trim($body) !== '' ? trim($body) : null;
    }

    /**
     * The store's pictures this gallery places, in the order the form sent
     * them.
     *
     * @return list<string>
     */
    public function imageIds(): array
    {
        $ids = $this->input('images');

        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_filter($ids, is_string(...)));
    }

    public function storeProfile(): StoreProfile
    {
        $section = $this->section();

        if ($section instanceof StoreSection) {
            return $section->storeProfile ?? throw new RuntimeException('A store section belongs to a store.');
        }

        $seller = $this->user('seller');

        return $seller instanceof Seller
            ? $seller->storeProfile()->firstOrFail()
            : throw new RuntimeException('The store section routes run behind the auth.seller middleware.');
    }

    public function section(): ?StoreSection
    {
        $section = $this->route('section');

        return $section instanceof StoreSection ? $section : null;
    }

    private function allows(StoreSectionField $field): bool
    {
        return $this->kind()->allows($field);
    }

    /**
     * The kind a new section names. An unreadable value falls back to a
     * story so `rules()` has a kind to shape itself with; the enum rule is
     * what refuses it.
     */
    private function requestedKind(): StoreSectionKind
    {
        return $this->enum('kind', StoreSectionKind::class) ?? StoreSectionKind::Story;
    }
}
