<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Listings\ListingRemovalKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use RuntimeException;
use Stringable;

final class RemoveListingRequest extends FormRequest
{
    /**
     * @return array<string, list<string|Stringable>>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(ListingRemovalKind::class)],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function kind(): ListingRemovalKind
    {
        return $this->enum('kind', ListingRemovalKind::class)
            ?? throw new RuntimeException('The kind rule admits only listing removal kinds.');
    }

    public function reason(): string
    {
        return $this->string('reason')->toString();
    }
}
