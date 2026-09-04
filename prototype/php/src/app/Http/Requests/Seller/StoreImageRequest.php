<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Actions\Store\StartStore;
use App\Domain\Store\StorePictureRole;
use App\Models\Seller;
use App\Models\StoreProfile;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use RuntimeException;
use Stringable;

/**
 * One picture onto the Store screen — the file rules every image upload in
 * the portal is held to, plus the role that says whether the new row also
 * becomes the store's portrait or its cover.
 */
final class StoreImageRequest extends FormRequest
{
    private const int MAX_IMAGE_KILOBYTES = 5120;

    public function authorize(): Response
    {
        return Gate::inspect('update', $this->storeProfile());
    }

    /**
     * @return array<string, list<string|Stringable>>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'image', 'mimes:jpeg,png,webp,gif', 'dimensions:min_width=1,min_height=1', 'max:'.self::MAX_IMAGE_KILOBYTES],
            'role' => ['required', Rule::enum(StorePictureRole::class)],
            'alt' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->storeProfile()->images()->count() >= StoreProfile::MAX_IMAGES) {
                $validator->errors()->add(
                    'image',
                    'This store already holds '.StoreProfile::MAX_IMAGES.' pictures, the most allowed.',
                );
            }
        });
    }

    public function uploadedImage(): UploadedFile
    {
        $file = $this->file('image');

        return $file instanceof UploadedFile ? $file : throw new RuntimeException('The image rule requires a file.');
    }

    public function role(): StorePictureRole
    {
        return $this->enum('role', StorePictureRole::class)
            ?? throw new RuntimeException('The role rule admits only portrait, cover, or gallery.');
    }

    public function alt(): ?string
    {
        $alt = $this->input('alt');

        return is_string($alt) && trim($alt) !== '' ? trim($alt) : null;
    }

    /**
     * The seller's store, minted on first reach the same way
     * {@see \App\Http\Controllers\Seller\StoreController::show()} mints it —
     * a POST to this route can be the first one, if a seller uploads a
     * picture before their first `GET /seller/store`.
     */
    public function storeProfile(): StoreProfile
    {
        $seller = $this->user('seller');

        return $seller instanceof Seller
            ? app(StartStore::class)($seller)
            : throw new RuntimeException('The store image routes run behind the auth.seller middleware.');
    }
}
