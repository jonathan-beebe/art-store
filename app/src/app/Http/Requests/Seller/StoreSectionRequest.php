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
    /**
     * Every section on the Store screen posts its own form under the same
     * field names, so the errors from one save go into a bag named for the
     * section that failed. The page reads that bag beside that section and
     * leaves the others untouched.
     */
    protected function prepareForValidation(): void
    {
        $this->errorBag = self::errorBagFor($this->section());
    }

    /** The bag a section's own errors land in. */
    public static function errorBagFor(?StoreSection $section): string
    {
        return $section instanceof StoreSection ? 'section-'.$section->id : 'section-new';
    }

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
            $rules['order'] = ['array'];
            $rules['order.*'] = ['integer', 'min:0', 'max:'.StoreSection::MAX_GALLERY_IMAGES];
        }

        return $rules;
    }

    /**
     * A field the kind does not use is an error, so a form that sends a
     * body to a gallery hears about it.
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
     * The store's pictures this gallery places, in the order the seller
     * numbered them. A picture the form sent no number for sorts last,
     * keeping the order the checkboxes arrived in.
     *
     * @return list<string>
     */
    public function imageIds(): array
    {
        $ids = $this->input('images');

        if (! is_array($ids)) {
            return [];
        }

        /** @var list<string> $chosen */
        $chosen = array_values(array_filter($ids, is_string(...)));
        $order = $this->order();

        usort($chosen, fn (string $a, string $b): int => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));

        return $chosen;
    }

    /**
     * The place the seller typed against each picture, keyed by picture id.
     *
     * @return array<string, int>
     */
    private function order(): array
    {
        $order = $this->input('order');

        if (! is_array($order)) {
            return [];
        }

        $places = [];

        foreach ($order as $imageId => $place) {
            if (is_string($imageId) && is_numeric($place)) {
                $places[$imageId] = (int) $place;
            }
        }

        return $places;
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
