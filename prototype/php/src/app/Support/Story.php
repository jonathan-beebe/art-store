<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Auth\ActorType;
use App\Domain\CarriesRefusalData;
use App\Domain\DomainRuleViolation;
use App\Logging\StoryEvent;
use App\Logging\StoryLevel;
use App\Logging\StoryPhase;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * How a unit of work tells its own story: `will` on the way in, then one of
 * `did`, `refused`, or `failed` on the way out, with `doing` for a long step
 * between them. docs/alignment.md §2.2 is the contract these phases keep.
 *
 * `tell()` is how work that runs inside one unit says all of that at once,
 * and the shape the actions use: the caller hands over the work and gets the
 * refusal and failure endings without writing them.
 *
 * `will` mints the `txn_id` the unit of work is read by, so every line
 * written before it ends — the action's own, and the ledger entries and
 * notifications that fall out of it — carries the same value. The open ids
 * are a stack because a unit of work can open inside another one; the
 * innermost is the one a line names.
 *
 * The request marks travel differently. `follows()`, `inSession()`, and
 * `actorIs()` put them in the logger's own context, so every line for the
 * rest of the request carries them without being handed them.
 */
final class Story
{
    private const string UNIT_PREFIX = 'txn';

    private const string SYSTEM_ACTOR = 'system';

    private const int MILLISECONDS = 1000;

    /**
     * The units of work open right now, innermost last.
     *
     * @var list<string>
     */
    private static array $units = [];

    private ?string $unit = null;

    private ?float $openedAt = null;

    private function __construct(private readonly StoryEvent $event) {}

    public static function for(StoryEvent $event): self
    {
        return new self($event);
    }

    /**
     * The request every line from here on belongs to.
     */
    public static function follows(string $requestId): void
    {
        Log::withContext(['request_id' => $requestId]);
    }

    /**
     * The browser every line from here on belongs to. It arrives after the
     * request id because the cookie holding it is only readable once the
     * middleware that decrypts cookies has run.
     */
    public static function inSession(string $sessionId): void
    {
        Log::withContext(['session_id' => $sessionId]);
    }

    /**
     * Who is behind the request. An anonymous customer counts as known: their
     * `cus_…` is what joins their lines together.
     */
    public static function actorIs(ActorType $actorType, string $actorId): void
    {
        Log::withContext(['actor_type' => $actorType->value, 'actor_id' => $actorId]);
    }

    /**
     * The application itself is the actor: a scheduled sweep answers to
     * nobody, so its lines carry `actor_type: system` and no actor id
     * (docs/alignment.md §2.1).
     */
    public static function asSystem(): void
    {
        Log::withContext(['actor_type' => self::SYSTEM_ACTOR]);
    }

    /**
     * Drops any unit of work left open by work that never reached its ending,
     * so the next request starts on an empty stack.
     */
    public static function forget(): void
    {
        self::$units = [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function will(string $message, array $data = []): self
    {
        if ($this->event->opensUnitOfWork()) {
            $this->unit = IdMint::of(self::UNIT_PREFIX);
            self::$units[] = $this->unit;
        }

        $this->openedAt = microtime(true);

        return $this->write(StoryPhase::Will, $this->event->level(), $message, $data);
    }

    /**
     * The whole story of one unit of work: `will` on the way in, the work
     * itself, and an ending whichever way the work leaves. The work names its
     * own `did` — only it knows what came out — while a `DomainRuleViolation`
     * ends it as `refused` and anything else as `failed`, both of them still
     * reaching the caller.
     *
     * Every path through here closes the unit, so the `txn_id` it minted
     * cannot outlive the work that opened it and name a later line.
     *
     * @template TResult
     *
     * @param  array<string, mixed>  $data
     * @param  callable(self): TResult  $work
     * @return TResult
     */
    public function tell(string $message, array $data, callable $work): mixed
    {
        $this->will($message, $data);

        try {
            return $work($this);
        } catch (DomainRuleViolation $violation) {
            $this->refused($violation->getMessage(), [...$data, ...self::refusalData($violation, $data)]);

            throw $violation;
        } catch (Throwable $error) {
            $this->failed($error, "{$message} broke", $data);

            throw $error;
        } finally {
            $this->close();
        }
    }

    /**
     * A step long enough to be worth seeing before the unit of work ends.
     *
     * @param  array<string, mixed>  $data
     */
    public function doing(string $message, array $data = []): self
    {
        return $this->write(StoryPhase::Doing, $this->event->level(), $message, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function did(string $message, array $data = []): self
    {
        return $this->write(StoryPhase::Did, $this->event->level(), $message, $data, $this->elapsed())->close();
    }

    /**
     * The core turned the work down and the world is unchanged.
     *
     * @param  array<string, mixed>  $data
     */
    public function refused(string $message, array $data = []): self
    {
        return $this->write(StoryPhase::Refused, $this->event->refusalLevel(), $message, $data)->close();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function failed(Throwable $error, string $message, array $data = []): self
    {
        return $this->write(StoryPhase::Failed, StoryLevel::Error, $message, $data, $this->elapsed(), $error)->close();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function write(
        StoryPhase $phase,
        StoryLevel $level,
        string $message,
        array $data,
        ?int $durationMs = null,
        ?Throwable $error = null,
    ): self {
        Log::log($level->psr(), $message, array_filter([
            'event' => $this->event->value,
            'phase' => $phase->value,
            'txn_id' => self::openUnit(),
            'data' => self::facts($data),
            'exception' => $error,
            'duration_ms' => $durationMs,
        ], fn (mixed $value): bool => $value !== null));

        return $this;
    }

    private function close(): self
    {
        if ($this->unit !== null) {
            array_pop(self::$units);
            $this->unit = null;
        }

        return $this;
    }

    /**
     * Wall time since the `will` line, in milliseconds. Null when nothing
     * opened this story, which is how a standalone `did` — a ledger entry, a
     * delivered notification — carries no duration.
     */
    private function elapsed(): ?int
    {
        return $this->openedAt === null ? null : (int) round((microtime(true) - $this->openedAt) * self::MILLISECONDS);
    }

    /**
     * A fact nobody has is not a fact, so an id that came back null leaves
     * the line rather than sitting in it as a null.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private static function facts(array $data): ?array
    {
        $facts = array_filter($data, fn (mixed $value): bool => $value !== null);

        return $facts === [] ? null : $facts;
    }

    private static function openUnit(): ?string
    {
        return self::$units === [] ? null : self::$units[array_key_last(self::$units)];
    }

    /**
     * A violation that names nothing beyond its message carries nothing more
     * onto the line it ends the story with, and one that names a key the unit
     * of work already named carries only the rest: the action's own facts win,
     * because the action is what the line is about.
     *
     * @param  array<string, mixed>  $data  what the unit of work already knows
     * @return array<string, mixed>
     */
    private static function refusalData(DomainRuleViolation $violation, array $data): array
    {
        return $violation instanceof CarriesRefusalData
            ? array_diff_key($violation->refusalData(), $data)
            : [];
    }
}
