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
 * The one place a docs/alignment.md §3 limit is checked and, if it holds,
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
    public function check(RateLimitName $name, string $key): void
    {
        $this->checkEach($name, [$key]);
    }

    /**
     * @param  list<string>  $keys  independent budgets checked under the same limit
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
            $limiterKey = $this->limiterKey($name, $key);

            if ($this->limiter->tooManyAttempts($limiterKey, $setting->maxAttempts)) {
                $this->refuse($name, $key, $this->limiter->availableIn($limiterKey));
            }
        }

        foreach ($keys as $key) {
            $this->limiter->hit($this->limiterKey($name, $key), $setting->decaySeconds);
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

    private function refuse(RateLimitName $name, string $key, int $retryAfterSeconds): never
    {
        Story::for(StoryEvent::RateLimitExceed)->refused("too many {$name->value} requests", [
            'limit' => $name->value,
            'key' => $key,
            'retry_after_seconds' => $retryAfterSeconds,
        ]);

        throw new RateLimitExceeded($name, $key, $retryAfterSeconds);
    }
}
