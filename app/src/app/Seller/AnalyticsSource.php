<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Seller\ActivityKind;
use App\Domain\Seller\FeedEvent;
use App\Domain\Seller\FeedIcon;
use App\Models\Listing;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * What the buyer did on the storefront before they bought: the browsing half
 * of the feed, read from the analytics store. The order and payment events
 * that store also carries are the order source's — read from the tables that
 * hold the money, where the amounts are.
 */
final readonly class AnalyticsSource implements ActivityFeedSource
{
    private const int LIMIT = 200;

    private const string SUBJECT_LISTING = 'listing';

    /**
     * @return list<FeedEvent>
     */
    public function events(FeedScope $scope): array
    {
        if ($scope->listingIds === []) {
            return [];
        }

        $rows = $this->rows($scope);
        $titles = $this->titlesOf($scope->listingIds);

        $events = [];

        foreach ($rows as $row) {
            $event = $this->toFeedEvent($row, $scope, $titles);

            if ($event instanceof FeedEvent) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * @return list<stdClass>
     */
    private function rows(FeedScope $scope): array
    {
        $names = array_map(fn (AnalyticsEventName $name): string => $name->value, self::browsingNames());

        $rows = DB::connection('analytics')->table('analytics_events')
            ->where('actor_id', $scope->customerId)
            ->whereIn('name', $names)
            ->where(function (Builder $query) use ($scope): void {
                $query
                    ->where(fn (Builder $listing): Builder => $listing
                        ->where('subject_type', self::SUBJECT_LISTING)
                        ->whereIn('subject_id', $scope->listingIds))
                    ->orWhere('subject_type', '!=', self::SUBJECT_LISTING);
            })
            // occurred_at ties within the same second; the id — a ULID —
            // breaks the tie in the order the rows were minted.
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();

        /** @var list<stdClass> $all */
        $all = array_values($rows->all());

        return $all;
    }

    /**
     * @param  array<string, string>  $titles
     */
    private function toFeedEvent(stdClass $row, FeedScope $scope, array $titles): ?FeedEvent
    {
        $name = is_string($row->name) ? AnalyticsEventName::tryFrom($row->name) : null;

        if (! $name instanceof AnalyticsEventName) {
            return null;
        }

        // A row that names none of this seller's pieces is another seller's
        // story, whatever its subject.
        $listingId = $this->listingIdOf($row, $scope);

        if ($listingId === null) {
            return null;
        }

        return new FeedEvent(
            occurredAt: $this->instantOf($row),
            kind: ActivityKind::Browse,
            icon: self::iconOf($name),
            actor: $scope->customerName,
            text: self::textOf($name, $titles[$listingId] ?? 'a piece'),
            link: route('seller.listings.show', $listingId),
        );
    }

    /**
     * The listing a row is about: its own subject, or — for a cart or an
     * order subject — the first of this seller's listings the row names.
     */
    private function listingIdOf(stdClass $row, FeedScope $scope): ?string
    {
        if ($row->subject_type === self::SUBJECT_LISTING) {
            return is_string($row->subject_id) ? $row->subject_id : null;
        }

        foreach ($this->dataListingIds($row) as $listingId) {
            if (in_array($listingId, $scope->listingIds, true)) {
                return $listingId;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function dataListingIds(stdClass $row): array
    {
        $decoded = is_string($row->data) ? json_decode($row->data, true) : null;
        $listingIds = is_array($decoded) ? ($decoded['listing_ids'] ?? []) : [];

        return is_array($listingIds)
            ? array_values(array_filter($listingIds, is_string(...)))
            : [];
    }

    private function instantOf(stdClass $row): DateTimeImmutable
    {
        $occurredAt = is_string($row->occurred_at) ? $row->occurred_at : 'now';

        return new DateTimeImmutable($occurredAt, new DateTimeZone('UTC'));
    }

    /**
     * @param  list<string>  $listingIds
     * @return array<string, string>
     */
    private function titlesOf(array $listingIds): array
    {
        $titles = [];

        foreach (Listing::query()->whereIn('id', $listingIds)->get(['id', 'title']) as $listing) {
            $titles[$listing->id] = $listing->title;
        }

        return $titles;
    }

    /**
     * @return list<AnalyticsEventName>
     */
    private static function browsingNames(): array
    {
        return [
            AnalyticsEventName::ListingView,
            AnalyticsEventName::ListingFavorite,
            AnalyticsEventName::ListingUnfavorite,
            AnalyticsEventName::ListingCartAdd,
            AnalyticsEventName::CheckoutOpen,
        ];
    }

    private static function iconOf(AnalyticsEventName $name): FeedIcon
    {
        return match ($name) {
            AnalyticsEventName::ListingFavorite, AnalyticsEventName::ListingUnfavorite => FeedIcon::Heart,
            AnalyticsEventName::ListingCartAdd, AnalyticsEventName::CheckoutOpen => FeedIcon::Cart,
            default => FeedIcon::Eye,
        };
    }

    private static function textOf(AnalyticsEventName $name, string $title): string
    {
        return match ($name) {
            AnalyticsEventName::ListingView => "viewed {$title}",
            AnalyticsEventName::ListingFavorite => "favorited {$title}",
            AnalyticsEventName::ListingUnfavorite => "took {$title} out of their favorites",
            AnalyticsEventName::ListingCartAdd => "added {$title} to their cart",
            default => 'opened checkout',
        };
    }
}
