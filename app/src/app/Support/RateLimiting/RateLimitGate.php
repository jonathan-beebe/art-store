<?php

declare(strict_types=1);

namespace App\Support\RateLimiting;

use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Domain\RateLimiting\RateLimitValue;
use App\Logging\StoryEvent;
use App\Support\Story;
use Illuminate\Cache\RateLimiter;

/**
 * The one place a docs/spec.md §3 limit is checked and, if it holds,
 * hit. Backed by `Illuminate\Cache\RateLimiter` over the default cache
 * store (`config/cache.php`: `database`), so a count survives a process
 * restart the way an in-memory counter would not.
 *
 * `checkEach()` looks at every key before recording a hit against any of
 * them, so a request refused on one key never leaves a mark against
 * another — `magic_link_request`'s email and ip budgets each trip on their
 * own count, independently of each other.
 */
final readonly class RateLimitGate
{
    public function __construct(private RateLimiter $limiter) {}

    /**
     * @throws RateLimitExceeded when $key has spent $name's budget
     */
    public function check(RateLimitName $name, string|EmailRateLimitKey $key): void
    {
        $this->checkEach($name, [$key]);
    }

    /**
     * @param  list<string|EmailRateLimitKey>  $keys  independent budgets checked under the same limit —
     *                                                an `EmailRateLimitKey` for an address, a plain
     *                                                string for anything else (docs/spec.md §3)
     *
     * @throws RateLimitExceeded when any of $keys has spent $name's budget
     */
    public function checkEach(RateLimitName $name, array $keys): void
    {
        $setting = $this->settingFor($name);

        if (! $setting->enabled) {
            return;
        }

        foreach ($keys as $key) {
            $limiterKey = $this->limiterKey($name, self::storageKey($key));

            if ($this->limiter->tooManyAttempts($limiterKey, $setting->maxAttempts)) {
                $this->refuse($name, $key, $this->limiter->availableIn($limiterKey));
            }
        }

        foreach ($keys as $key) {
            $this->limiter->hit($this->limiterKey($name, self::storageKey($key)), $setting->decaySeconds);
        }
    }

    private function settingFor(RateLimitName $name): RateLimitValue
    {
        /** @var RateLimitValue $setting */
        $setting = config("rate_limits.{$name->value}");

        return $setting;
    }

    private function limiterKey(RateLimitName $name, string $key): string
    {
        return "{$name->value}:{$key}";
    }

    private function refuse(RateLimitName $name, string|EmailRateLimitKey $key, int $retryAfterSeconds): never
    {
        $logged = self::loggedKey($key);

        Story::for(StoryEvent::RateLimitExceed)->refused("too many {$name->value} requests", [
            'limit' => $name->value,
            'key' => $logged,
            'retry_after_seconds' => $retryAfterSeconds,
        ]);

        throw new RateLimitExceeded($name, $logged, $retryAfterSeconds);
    }

    /**
     * The limiter's own bucket identity — stable across a redeploy, since it
     * decides which counter a hit lands on.
     */
    private static function storageKey(string|EmailRateLimitKey $key): string
    {
        return $key instanceof EmailRateLimitKey ? $key->key() : $key;
    }

    /**
     * What a `rate_limit.exceed` line, or the exception a catcher logs
     * itself, is allowed to carry (docs/spec.md §3): never the full
     * digest an `EmailRateLimitKey` counts the budget against.
     */
    private static function loggedKey(string|EmailRateLimitKey $key): string
    {
        return $key instanceof EmailRateLimitKey ? $key->logged() : $key;
    }
}
