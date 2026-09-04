<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

it('names the two entities a jump can land on', function (): void {
    expect(JumpKind::cases())->toBe([JumpKind::Listing, JumpKind::Actor]);
});
