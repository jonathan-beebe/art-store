<?php

declare(strict_types=1);

namespace App\Logging;

/**
 * Where a line sits in the story of one unit of work: what is about to
 * happen, a long step along the way, and the one line that ends it.
 */
enum StoryPhase: string
{
    case Will = 'will';
    case Doing = 'doing';
    case Did = 'did';

    /**
     * The core turned the work down. The world is unchanged, so the reader is
     * being told a rule held, not that something broke.
     */
    case Refused = 'refused';

    /**
     * Something nobody planned for. The line carries the exception.
     */
    case Failed = 'failed';
}
