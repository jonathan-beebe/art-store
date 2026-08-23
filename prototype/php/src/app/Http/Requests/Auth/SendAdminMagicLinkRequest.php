<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Domain\Auth\EmailNormalizer;
use App\Models\Admin;
use Illuminate\Foundation\Http\FormRequest;

final class SendAdminMagicLinkRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
        ];
    }

    public function email(): string
    {
        return $this->string('email')->toString();
    }

    /**
     * Whether an admin row exists for the submitted address. The controller
     * sends the same "check your email" response either way, so this stays
     * out of the validation rules — a rule that fails here would answer the
     * question a validation error is never supposed to answer.
     */
    public function admits(): bool
    {
        return Admin::query()->where('email', EmailNormalizer::normalize($this->email()))->exists();
    }
}
