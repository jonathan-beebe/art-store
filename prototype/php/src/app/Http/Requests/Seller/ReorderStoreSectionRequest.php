<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Store\StoreSectionMove;
use App\Models\StoreProfile;
use App\Models\StoreSection;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use RuntimeException;
use Stringable;

final class ReorderStoreSectionRequest extends FormRequest
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
        return ['direction' => ['required', Rule::enum(StoreSectionMove::class)]];
    }

    public function direction(): StoreSectionMove
    {
        return $this->enum('direction', StoreSectionMove::class)
            ?? throw new RuntimeException('The direction rule admits only up or down.');
    }

    public function section(): StoreSection
    {
        $section = $this->route('section');

        return $section instanceof StoreSection
            ? $section
            : throw new RuntimeException('The reorder route binds a store section.');
    }

    private function storeProfile(): StoreProfile
    {
        return $this->section()->storeProfile ?? throw new RuntimeException('A store section belongs to a store.');
    }
}
