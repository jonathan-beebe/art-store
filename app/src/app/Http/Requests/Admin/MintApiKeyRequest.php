<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class MintApiKeyRequest extends FormRequest
{
    public const int NAME_MAX = 100;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX],
        ];
    }

    public function name(): string
    {
        return $this->string('name')->trim()->toString();
    }
}
