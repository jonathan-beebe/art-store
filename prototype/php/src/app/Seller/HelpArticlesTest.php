<?php

declare(strict_types=1);

namespace App\Seller;

use RuntimeException;

it('reads all four shipped articles, one per topic', function (): void {
    $slugs = collect((new HelpArticles)->all())->pluck('slug')->all();

    expect($slugs)->toHaveCount(4)
        ->and($slugs)->toContain('when-money-reaches-your-account')
        ->and($slugs)->toContain('printing-a-label-from-an-order')
        ->and($slugs)->toContain('what-a-listing-needs-before-it-can-go-live')
        ->and($slugs)->toContain('turning-a-question-into-an-faq');
});

it('finds an article by its slug', function (): void {
    $article = (new HelpArticles)->find('printing-a-label-from-an-order') ?? throw new RuntimeException('Expected an article.');

    expect($article->title)->toBe('Printing a label from an order')
        ->and($article->paragraphs)->not->toBeEmpty();
});

it('answers null for a slug no article carries', function (): void {
    expect((new HelpArticles)->find('not-a-real-article'))->toBeNull();
});

it('groups articles by topic in the getting-paid, shipping, listings, messages order', function (): void {
    $groups = array_keys((new HelpArticles)->grouped());

    expect($groups)->toBe(['Getting paid', 'Shipping', 'Listings', 'Messages']);
});

it('gives every group exactly one article in this cut', function (): void {
    foreach ((new HelpArticles)->grouped() as $group => $articles) {
        expect($articles)->toHaveCount(1);
    }
});

it('parses the files only once across repeated reads of the same instance', function (): void {
    $helpArticles = new HelpArticles;

    expect($helpArticles->all())->toBe($helpArticles->all());
});
