<?php

declare(strict_types=1);

namespace App\Support\Messaging;

/**
 * A message inbox's three independent facets (docs/messaging.md § "Inbox
 * filters and the seller's queue"): `domain` picks the tab, `types` and
 * `statuses` are the popover's two checkbox groups, OR'd within a group and
 * AND'd against each other and against the domain. Both `MessagesQueryRequest`
 * classes already resolve an absent facet to its default before building one
 * of these — `types` holds every type and `statuses` holds the portal's
 * default statuses when the request carried none — so nothing downstream
 * re-derives a default of its own.
 */
final readonly class InboxQuery
{
    /**
     * @param  list<string>  $types
     * @param  list<string>  $statuses
     */
    public function __construct(
        public string $domain,
        public array $types,
        public array $statuses,
    ) {}

    public function hasType(string $type): bool
    {
        return in_array($type, $this->types, true);
    }

    public function hasStatus(string $status): bool
    {
        return in_array($status, $this->statuses, true);
    }

    /**
     * The full current selection — a row link, a hidden form field, or a
     * redirect that must keep the pane it came from rather than snap back to
     * the index route's defaults.
     *
     * @return array{domain: string, type: list<string>, status: list<string>}
     */
    public function toRouteParams(): array
    {
        return ['domain' => $this->domain, 'type' => $this->types, 'status' => $this->statuses];
    }

    /**
     * The popover's Reset target: this domain, and nothing else — the same
     * shape an index route with no query string at all resolves to.
     *
     * @return array{domain: string}
     */
    public function resetRouteParams(): array
    {
        return ['domain' => $this->domain];
    }

    /**
     * The Filter control's count pill: how many Type boxes read unchecked
     * plus how many Status choices differ from the given default. The domain
     * tab never counts toward it — it sits outside the popover.
     *
     * @param  list<string>  $allTypes
     * @param  list<string>  $defaultStatuses
     */
    public function changesFromDefault(array $allTypes, array $defaultStatuses): int
    {
        $uncheckedTypes = count(array_diff($allTypes, $this->types));
        $changedStatuses = count(array_diff($this->statuses, $defaultStatuses))
            + count(array_diff($defaultStatuses, $this->statuses));

        return $uncheckedTypes + $changedStatuses;
    }

    /**
     * Two optional kind lists narrowed to their overlap — how a domain tab
     * and the Type checkbox group, each mapped to conversation kinds on its
     * own, combine as one AND. `null` means that facet admits every kind;
     * two `null`s leave the query unrestricted. A combination that shares no
     * kind (Sellers domain with the Questions type, say) comes back an empty
     * list rather than null, which the caller's `ofKind()` turns into "no
     * rows" rather than "every row".
     *
     * @template T
     *
     * @param  list<T>|null  $domainKinds
     * @param  list<T>|null  $typeKinds
     * @return list<T>|null
     */
    public static function intersectKinds(?array $domainKinds, ?array $typeKinds): ?array
    {
        // `array_intersect` compares by string cast, which a backed enum
        // does not support — filtering with a strict `in_array` instead
        // works for any value, enum or scalar.
        return match (true) {
            $domainKinds === null && $typeKinds === null => null,
            $domainKinds === null => $typeKinds,
            $typeKinds === null => $domainKinds,
            default => array_values(array_filter(
                $domainKinds,
                fn ($kind) => in_array($kind, $typeKinds, true),
            )),
        };
    }
}
