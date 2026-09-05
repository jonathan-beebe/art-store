<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Messaging\FaqDraft;
use App\Models\Listing;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

final class UpdateFaqRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->listing());
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:'.FaqDraft::QUESTION_MAX_LENGTH],
            'answer' => ['required', 'string', 'max:'.FaqDraft::ANSWER_MAX_LENGTH],
        ];
    }

    public function draft(): FaqDraft
    {
        return FaqDraft::of($this->string('question')->toString(), $this->string('answer')->toString());
    }

    private function listing(): Listing
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            ? $listing
            : throw new RuntimeException('The faq route binds a listing.');
    }
}
