<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class RunPayoutRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'as_of' => ['nullable', 'date'],
        ];
    }

    /**
     * The date the run settles as of, or $default when the admin left the
     * field blank — the same "no `as_of` means today" the CLI applies.
     */
    public function asOf(DateTimeImmutable $default): DateTimeImmutable
    {
        return $this->date('as_of')?->toDateTimeImmutable() ?? $default;
    }
}
