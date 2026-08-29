<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\Listing;
use App\Models\ListingImage;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * One upload onto the Images screen — the same file rules the create form
 * already holds an image upload to, plus the cap on how many a listing may
 * carry at once.
 */
final class ListingImageRequest extends FormRequest
{
    private const int MAX_IMAGE_KILOBYTES = 5120;

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
            'image' => ['required', 'file', 'image', 'mimes:jpeg,png,webp,gif', 'dimensions:min_width=1,min_height=1', 'max:'.self::MAX_IMAGE_KILOBYTES],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->listing()->images()->count() >= ListingImage::MAX_PER_LISTING) {
                $validator->errors()->add(
                    'image',
                    'This listing already holds '.ListingImage::MAX_PER_LISTING.' images, the most allowed.',
                );
            }
        });
    }

    public function uploadedImage(): UploadedFile
    {
        $file = $this->file('image');

        return $file instanceof UploadedFile ? $file : throw new RuntimeException('The image rule requires a file.');
    }

    public function listing(): Listing
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            ? $listing
            : throw new RuntimeException('The listing image route binds a listing.');
    }
}
