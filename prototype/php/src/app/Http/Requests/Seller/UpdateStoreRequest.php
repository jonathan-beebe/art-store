<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Store\StoreLinkKind;
use App\Domain\Store\StoreSlug;
use App\Domain\Store\StoreVisibility;
use App\Models\Seller;
use App\Models\StoreProfile;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use RuntimeException;
use Stringable;

/**
 * The Store screen's one save: identity, address, tagline, location, the
 * links under the story, and whether buyers can open the page.
 *
 * The address is unique across `store_slugs`, so a name another store
 * retired is refused too — a redirect that could land on either store is
 * never created.
 */
final class UpdateStoreRequest extends FormRequest
{
    private const int MAX_LINK_LENGTH = 255;

    public function authorize(): bool
    {
        return $this->user('seller') instanceof Seller;
    }

    /**
     * @return array<string, list<string|Stringable>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'min:'.StoreSlug::MIN_LENGTH,
                'max:'.StoreSlug::MAX_LENGTH,
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $this->addressIsFree(),
            ],
            'tagline' => ['nullable', 'string', 'max:'.StoreProfile::MAX_TAGLINE_LENGTH],
            'location' => ['nullable', 'string', 'max:255'],
            'visibility' => ['required', Rule::enum(StoreVisibility::class)],
            'links' => ['array'],
            'links.'.StoreLinkKind::Website->value => ['nullable', 'string', 'url:http,https', 'max:'.self::MAX_LINK_LENGTH],
            'links.'.StoreLinkKind::Instagram->value => ['nullable', 'string', 'max:'.self::MAX_LINK_LENGTH, 'regex:/^@?[A-Za-z0-9._]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'The store address takes lowercase letters, numbers, and hyphens.',
            'slug.unique' => 'Another store already answers to that address.',
            'links.instagram.regex' => 'The Instagram field takes a handle, not a link.',
        ];
    }

    public function name(): string
    {
        return $this->required('name');
    }

    public function slug(): string
    {
        return $this->required('slug');
    }

    public function tagline(): ?string
    {
        return $this->trimmedOrNull('tagline');
    }

    public function location(): ?string
    {
        return $this->trimmedOrNull('location');
    }

    public function visibility(): StoreVisibility
    {
        return $this->enum('visibility', StoreVisibility::class)
            ?? throw new RuntimeException('The visibility rule admits only published or hidden.');
    }

    /**
     * What the seller typed for each kind, with the blanks dropped — the
     * kinds that survive are the store's links, in case order.
     *
     * @return array<string, string>
     */
    public function links(): array
    {
        $links = [];

        foreach (StoreLinkKind::cases() as $kind) {
            $url = $this->trimmedOrNull('links.'.$kind->value);

            if ($url !== null) {
                $links[$kind->value] = $url;
            }
        }

        return $links;
    }

    /**
     * Free of every address any other store answers to or has answered to.
     * The seller's own rows are excluded, so saving the form without
     * touching the address is not a collision with itself.
     */
    private function addressIsFree(): Stringable
    {
        $seller = $this->user('seller');
        $profileId = $seller instanceof Seller ? $seller->storeProfile()->value('id') : null;

        $rule = Rule::unique('store_slugs', 'slug');

        return is_string($profileId)
            ? $rule->where(fn (Builder $query): Builder => $query->where('store_profile_id', '!=', $profileId))
            : $rule;
    }

    /**
     * A field the rules mark `required`, read back with the type those
     * rules already guarantee.
     */
    private function required(string $key): string
    {
        $value = $this->validated($key);

        return is_string($value) ? $value : throw new RuntimeException("The {$key} rule requires a string.");
    }

    private function trimmedOrNull(string $key): ?string
    {
        $value = $this->validated($key);

        if (! is_string($value)) {
            return null;
        }

        return trim($value) === '' ? null : trim($value);
    }
}
