<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Configurator\DescriptionSectionMove;
use App\Models\Listing;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use RuntimeException;

final class ReorderDescriptionSectionRequest extends FormRequest
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
        return ['direction' => ['required', Rule::enum(DescriptionSectionMove::class)]];
    }

    public function direction(): DescriptionSectionMove
    {
        return $this->enum('direction', DescriptionSectionMove::class) ?? throw new RuntimeException('The direction rule admits only up or down.');
    }

    public function listing(): Listing
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            ? $listing
            : throw new RuntimeException('The reorder route binds a listing.');
    }
}
