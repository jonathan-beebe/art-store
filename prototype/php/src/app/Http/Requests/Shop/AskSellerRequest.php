<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

use App\Domain\Messaging\MessageBody;
use App\Models\Listing;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use RuntimeException;

final class AskSellerRequest extends FormRequest
{
    /**
     * A question only lands on a listing a shopper could otherwise see —
     * the same storefront-visibility rule the listing page itself applies.
     */
    public function authorize(): Response
    {
        return $this->listing()->status->isOnStorefront()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.MessageBody::MAX_LENGTH],
        ];
    }

    public function body(): MessageBody
    {
        return MessageBody::of($this->string('body')->toString());
    }

    private function listing(): Listing
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            ? $listing
            : throw new RuntimeException('The question route binds a listing.');
    }
}
