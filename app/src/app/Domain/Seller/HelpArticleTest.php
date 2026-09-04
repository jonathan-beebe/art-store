<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use InvalidArgumentException;

it('parses the front matter and splits the body into paragraphs', function (): void {
    $article = HelpArticle::fromMarkdown(<<<'MD'
        ---
        group: Getting paid
        title: When money reaches your account
        slug: when-money-reaches-your-account
        position: 1
        ---

        Every sale is held in escrow the moment the buyer pays.

        Payout periods run Monday to Sunday.
        MD);

    expect($article->slug)->toBe('when-money-reaches-your-account')
        ->and($article->group)->toBe('Getting paid')
        ->and($article->title)->toBe('When money reaches your account')
        ->and($article->position)->toBe(1)
        ->and($article->paragraphs)->toBe([
            'Every sale is held in escrow the moment the buyer pays.',
            'Payout periods run Monday to Sunday.',
        ]);
});

it('defaults position to zero when the front matter omits it', function (): void {
    $article = HelpArticle::fromMarkdown(<<<'MD'
        ---
        group: Shipping
        title: Printing a label
        slug: printing-a-label
        ---

        Open the order and choose a carrier.
        MD);

    expect($article->position)->toBe(0);
});

it('collapses a paragraph\'s internal line breaks and extra whitespace to single spaces', function (): void {
    $article = HelpArticle::fromMarkdown(<<<'MD'
        ---
        group: Listings
        title: Photos
        slug: photos
        ---

        A title,
        a price,
        and a photo.
        MD);

    expect($article->paragraphs)->toBe(['A title, a price, and a photo.']);
});

it('refuses a file with no front matter', function (): void {
    HelpArticle::fromMarkdown('Just a paragraph, no front matter.');
})->throws(InvalidArgumentException::class, 'front matter');

it('refuses front matter missing a required field', function (): void {
    HelpArticle::fromMarkdown(<<<'MD'
        ---
        group: Messages
        title: Turning a question into an FAQ
        ---

        Publish the answer.
        MD);
})->throws(InvalidArgumentException::class, 'slug');
