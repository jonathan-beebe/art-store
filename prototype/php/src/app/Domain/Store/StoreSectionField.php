<?php

declare(strict_types=1);

namespace App\Domain\Store;

/**
 * A field a store section can carry. {@see StoreSectionKind::allows()} says
 * which of them a kind uses, and the form request refuses the rest at the
 * edge.
 */
enum StoreSectionField: string
{
    case Heading = 'heading';
    case Body = 'body';
    case Images = 'images';
}
