<?php

declare(strict_types=1);

namespace App\Domain\Seeding;

use DateTimeImmutable;

/**
 * One seeded visitor's session: a session id, an ip, a traffic origin, and
 * the script of steps they carry out — plus, for a session that names a
 * person ({@see SessionKind::NewSignup}, {@see SessionKind::ReturningVerify}),
 * the roster index that names them. `personIndex` is null for
 * {@see SessionKind::AnonymousBrowse}: that session never names itself.
 */
final readonly class Session
{
    /**
     * @param  list<VisitStep>  $steps
     */
    public function __construct(
        public int $dayIndex,
        public DateTimeImmutable $at,
        public string $sessionId,
        public string $ip,
        public SessionKind $kind,
        public ?int $personIndex,
        public ChannelPick $channel,
        public array $steps,
    ) {}
}
