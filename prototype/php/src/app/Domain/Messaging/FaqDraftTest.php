<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use App\Domain\DomainRuleViolation;

it('holds a question and answer within their limits', function (): void {
    $draft = FaqDraft::of('Does this ship internationally?', 'Yes, worldwide.');

    expect($draft->question)->toBe('Does this ship internationally?')
        ->and($draft->answer)->toBe('Yes, worldwide.');
});

it('rejects a question past its limit', function (): void {
    $question = str_repeat('a', FaqDraft::QUESTION_MAX_LENGTH + 1);

    expect(fn () => FaqDraft::of($question, 'Yes.'))->toThrow(DomainRuleViolation::class);
});

it('rejects an answer past its limit', function (): void {
    $answer = str_repeat('a', FaqDraft::ANSWER_MAX_LENGTH + 1);

    expect(fn () => FaqDraft::of('Ships internationally?', $answer))->toThrow(DomainRuleViolation::class);
});

it('accepts a question and an answer at exactly their limits', function (): void {
    $question = str_repeat('a', FaqDraft::QUESTION_MAX_LENGTH);
    $answer = str_repeat('b', FaqDraft::ANSWER_MAX_LENGTH);

    $draft = FaqDraft::of($question, $answer);

    expect($draft->question)->toBe($question)
        ->and($draft->answer)->toBe($answer);
});
