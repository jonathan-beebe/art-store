<?php

declare(strict_types=1);

namespace App\Logging\Admin;

/**
 * One row of the `group=1` view: a request's whole story, summarized off its
 * root `http.request` will/did pair — method, path, status, duration — or,
 * for a line with no `request_id`, the line itself.
 */
final readonly class LogRequestGroup
{
    /**
     * @param  'request'|'line'  $kind
     * @param  list<LogRow>  $lines
     */
    public function __construct(
        public string $key,
        public string $kind,
        public int $lineCount,
        public string $lastTs,
        public ?string $method,
        public ?string $path,
        public ?int $status,
        public ?int $durationMs,
        public ?string $level,
        public ?string $msg,
        public array $lines,
    ) {}
}
