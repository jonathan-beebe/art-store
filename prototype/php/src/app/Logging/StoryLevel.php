<?php

declare(strict_types=1);

namespace App\Logging;

/**
 * The four severities a log line carries. PSR-3 spells the third one
 * `warning`; the payload spells it `warn`, so the two spellings meet here
 * rather than at every call site.
 */
enum StoryLevel: string
{
    case Debug = 'debug';
    case Info = 'info';
    case Warn = 'warn';
    case Error = 'error';

    public function psr(): string
    {
        return $this === self::Warn ? 'warning' : $this->value;
    }
}
