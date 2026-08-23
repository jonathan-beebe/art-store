<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Messaging\FaqDraft;
use App\Models\Listing;
use App\Models\Message;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use RuntimeException;
use Stringable;

final class PublishFaqRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->listing());
    }

    /**
     * @return array<string, list<string|Stringable>>
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:'.FaqDraft::QUESTION_MAX_LENGTH],
            'answer' => ['required', 'string', 'max:'.FaqDraft::ANSWER_MAX_LENGTH],
            // The message a "Publish as FAQ" form pre-fills from; nullable
            // because an entry can be composed from scratch too. Scoped to
            // this listing's own threads, so a tampered id names no row.
            'source_message_id' => [
                'nullable',
                'integer',
                Rule::exists('messages', 'id')->where(
                    fn (Builder $query) => $query->whereIn(
                        'conversation_id',
                        fn (Builder $conversations) => $conversations->select('id')->from('conversations')->where('listing_id', $this->listing()->id),
                    )
                ),
            ],
        ];
    }

    public function draft(): FaqDraft
    {
        return FaqDraft::of($this->string('question')->toString(), $this->string('answer')->toString());
    }

    public function sourceMessage(): ?Message
    {
        return $this->filled('source_message_id') ? Message::find($this->integer('source_message_id')) : null;
    }

    public function listing(): Listing
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            ? $listing
            : throw new RuntimeException('The faq route binds a listing.');
    }
}
