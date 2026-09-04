<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Domain\Auth\LocalRedirect;
use Illuminate\Foundation\Http\FormRequest;

final class SendMagicLinkRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'redirect_to' => ['nullable', 'string'],
        ];
    }

    public function email(): string
    {
        return $this->string('email')->toString();
    }

    /**
     * @return string|null null when the field is absent or would send the
     *                     visitor off this site
     */
    public function redirectTo(): ?string
    {
        return LocalRedirect::keepIfLocal($this->string('redirect_to')->toString(), url('/'));
    }
}
