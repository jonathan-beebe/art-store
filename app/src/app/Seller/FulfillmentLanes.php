<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Auth\ActorType;
use App\Domain\Fulfillment\FulfillmentLane;
use App\Domain\Fulfillment\LaneFilter;
use App\Models\Fulfillment;
use App\Models\FulfillmentEvent;
use App\Models\Message;
use App\Models\Seller;
use App\Support\ListPaneWindow;
use App\Support\ParcelLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\JoinClause;

/**
 * The orders list pane: how many parcels each lane holds, and the rows of
 * the one the seller is looking at.
 *
 * The counts come from one grouped read over status and "has a completed
 * step"; the rows come from the same two facts expressed as a where clause,
 * so a tab's number and the rows under it are read from one rule.
 */
final readonly class FulfillmentLanes
{
    /**
     * @return array<string, NavLink> keyed by the lane's own value
     */
    public function tabs(Seller $seller, LaneFilter $current): array
    {
        $counts = $this->counts($seller);
        $tabs = [];

        foreach (LaneFilter::cases() as $lane) {
            $tabs[$lane->value] = new NavLink(
                label: $lane->label(),
                href: route('seller.orders.index', ['lane' => $lane->value]),
                active: $lane === $current,
                count: $lane->isCounted() ? ($counts[$lane->value] ?? 0) : null,
            );
        }

        return $tabs;
    }

    /**
     * One lane's window. The parcel the detail route has open keeps its
     * place in the pane even when the lane it belongs to holds more rows
     * than the window shows.
     */
    public function pane(Seller $seller, LaneFilter $lane, ?Fulfillment $open = null): OrderPane
    {
        $window = ListPaneWindow::of($this->query($seller, $lane), $open);
        $fulfillments = $window->items->load('latestCompletedStep');
        $notes = $this->notes($fulfillments);

        $rows = array_map(fn (Fulfillment $fulfillment): OrderRow => new OrderRow(
            id: $fulfillment->id,
            href: route('seller.orders.show', ['fulfillment' => $fulfillment->id, 'lane' => $lane->value]),
            selected: $open !== null && $open->id === $fulfillment->id,
            buyer: $fulfillment->order->shipping_name,
            itemLabel: ParcelLine::label($fulfillment),
            subtotal: $fulfillment->subtotal()->format(),
            statusLabel: $fulfillment->status->label(),
            tint: $fulfillment->status->badgeTint(),
            placed: $fulfillment->order->placed_at->format('M j'),
            note: $notes[$fulfillment->id] ?? null,
        ), $fulfillments->all());

        return new OrderPane($lane, array_values($rows), $window->total);
    }

    /**
     * The lane's rows, oldest first where a buyer is waiting. A fulfillment
     * is created when the order is paid, and its id is a ULID minted at the
     * same moment, so the pair orders the pile the way it filled up.
     *
     * @return Builder<Fulfillment>
     */
    private function query(Seller $seller, LaneFilter $lane): Builder
    {
        $direction = $lane->oldestFirst() ? 'asc' : 'desc';

        return Fulfillment::query()
            ->whereBelongsTo($seller)
            ->inLane($lane)
            ->with([
                'order',
                'order.items' => fn (Relation $items) => $items->where('seller_id', $seller->id),
            ])
            ->orderBy('created_at', $direction)
            ->orderBy('id', $direction);
    }

    /**
     * How many parcels each lane holds, keyed by the lane's value.
     *
     * @return array<string, int>
     */
    private function counts(Seller $seller): array
    {
        $counts = [];

        foreach (Fulfillment::query()->whereBelongsTo($seller)->countedByLane()->get() as $row) {
            $lane = FulfillmentLane::forStarted($row->status, $row->started);
            $counts[$lane->value] = ($counts[$lane->value] ?? 0) + $row->tally;
        }

        return $counts;
    }

    /**
     * The one line a row carries beyond its own facts: the buyer's
     * unanswered question, or the last step the seller marked done.
     *
     * @param  Collection<int, Fulfillment>  $fulfillments  carrying `latestCompletedStep`
     * @return array<string, string>
     */
    private function notes(Collection $fulfillments): array
    {
        if ($fulfillments->isEmpty()) {
            return [];
        }

        $ids = array_values($fulfillments->map(fn (Fulfillment $fulfillment): string => $fulfillment->id)->all());

        return [...$this->completedSteps($fulfillments), ...$this->unansweredQuestions($ids)];
    }

    /**
     * @param  Collection<int, Fulfillment>  $fulfillments  carrying `latestCompletedStep`
     * @return array<string, string>
     */
    private function completedSteps(Collection $fulfillments): array
    {
        $notes = [];

        foreach ($fulfillments as $fulfillment) {
            if ($fulfillment->latestCompletedStep instanceof FulfillmentEvent) {
                $notes[$fulfillment->id] = $fulfillment->latestCompletedStep->stepLabel();
            }
        }

        return $notes;
    }

    /**
     * The newest unread customer message per fulfillment, one grouped query
     * over a join to its thread, narrowed away from the whole backlog.
     *
     * @param  list<string>  $fulfillmentIds
     * @return array<string, string>
     */
    private function unansweredQuestions(array $fulfillmentIds): array
    {
        $unread = Message::query()
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->whereIn('conversations.fulfillment_id', $fulfillmentIds)
            ->where('messages.sender_type', ActorType::Customer->value)
            ->whereNull('messages.read_at');

        $latest = (clone $unread)
            ->selectRaw('conversations.fulfillment_id as fulfillment_id, max(messages.sent_at) as sent_at')
            ->groupBy('conversations.fulfillment_id');

        $rows = $unread
            ->joinSub($latest, 'latest_message', function (JoinClause $join): void {
                $join->on('conversations.fulfillment_id', '=', 'latest_message.fulfillment_id')
                    ->on('messages.sent_at', '=', 'latest_message.sent_at');
            })
            ->toBase()
            ->get(['conversations.fulfillment_id as fulfillment_id', 'messages.body as body']);

        $notes = [];

        foreach ($rows as $row) {
            // A tie on sent_at keeps the first row read for a parcel.
            $notes[self::text($row->fulfillment_id)] ??= self::text($row->body);
        }

        return $notes;
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
