<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

use InvalidArgumentException;

/**
 * A funnel's own ordered list of steps, validated: two or more names, each
 * a known {@see AnalyticsEventName}, none repeated. Visitors is every
 * funnel's implied first step and never appears in this list — {@see of()}
 * validates only the steps an admin names beyond it. `App\Models\Funnel`
 * and `App\Http\Requests\Admin\FunnelRequest` both build one of these
 * before storing a funnel, so an unknown or repeated name never reaches
 * the database.
 */
final readonly class FunnelDefinition
{
    /**
     * @param  list<AnalyticsEventName>  $steps
     */
    private function __construct(
        public array $steps,
    ) {}

    /**
     * @param  list<string>  $names
     *
     * @throws InvalidArgumentException when fewer than two names are given,
     *                                  a name is not in the analytics
     *                                  vocabulary, or a name repeats
     */
    public static function of(array $names): self
    {
        if (count($names) < 2) {
            throw new InvalidArgumentException('A funnel needs at least two steps.');
        }

        $steps = [];

        foreach ($names as $name) {
            $step = AnalyticsEventName::tryFrom($name);

            if ($step === null) {
                throw new InvalidArgumentException("\"{$name}\" is not a known event name.");
            }

            if (in_array($step, $steps, true)) {
                throw new InvalidArgumentException("\"{$name}\" is repeated.");
            }

            $steps[] = $step;
        }

        return new self($steps);
    }

    /**
     * The built-in storefront funnel: a shopper viewing a listing through
     * to paying for it. Favorites sits off this path — docs/funnel.md
     * renders it as a side count on the viewed step.
     */
    public static function storefront(): self
    {
        return self::of([
            AnalyticsEventName::ListingView->value,
            AnalyticsEventName::ListingCartAdd->value,
            AnalyticsEventName::CheckoutOpen->value,
            AnalyticsEventName::OrderPlace->value,
            AnalyticsEventName::OrderPay->value,
        ]);
    }
}
