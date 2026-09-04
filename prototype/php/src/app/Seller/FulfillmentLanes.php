<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Auth\ActorType;
use App\Domain\Fulfillment\FulfillmentEventKind;
use App\Domain\Fulfillment\FulfillmentLane;
use App\Domain\Fulfillment\LaneFilter;
use App\Models\Conversation;
use App\Models\Fulfillment;
use App\Models\FulfillmentEvent;
use App\Models\Message;
use App\Models\Seller;
use App\Support\ListPaneWindow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

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
     * @return list<LaneTab>
     */
    public function tabs(Seller $seller, LaneFilter $current): array
    {
        $counts = $this->counts($seller);

        return array_map(fn (LaneFilter $lane): LaneTab => new LaneTab(
            lane: $lane,
            href: route('seller.orders.index', ['lane' => $lane->value]),
            active: $lane === $current,
            count: $lane->isCounted() ? ($counts[$lane->value] ?? 0) : null,
        ), LaneFilter::cases());
    }

    /**
     * One lane's window. The parcel the detail route has open keeps its
     * place in the pane even when the lane it belongs to holds more rows
     * than the window shows.
     */
    public function pane(Seller $seller, LaneFilter $lane, ?Fulfillment $open = null): OrderPane
    {
        $window = ListPaneWindow::of($this->query($seller, $lane), $open);

        /** @var list<Fulfillment> $fulfillments */
        $fulfillments = $window->items->all();
        $notes = $this->notes(array_map(fn (Fulfillment $fulfillment): string => $fulfillment->id, $fulfillments));

        $rows = array_map(fn (Fulfillment $fulfillment): OrderRow => new OrderRow(
            id: $fulfillment->id,
            href: route('seller.orders.show', ['fulfillment' => $fulfillment->id, 'lane' => $lane->value]),
            selected: $open !== null && $open->id === $fulfillment->id,
            buyer: $fulfillment->order->shipping_name,
            itemLabel: $fulfillment->itemLabel(),
            subtotal: $fulfillment->subtotal()->format(),
            statusLabel: $fulfillment->status->label(),
            tint: $fulfillment->status->tint(),
            placed: $fulfillment->order->placed_at->format('M j'),
            note: $notes[$fulfillment->id] ?? null,
        ), $fulfillments);

        return new OrderPane($lane, $rows, $window->total);
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
     * The one line a row carries beyond its own facts: what the buyer asked
     * and nobody has answered, else the last step the seller marked done.
     *
     * @param  list<string>  $fulfillmentIds
     * @return array<string, string>
     */
    private function notes(array $fulfillmentIds): array
    {
        if ($fulfillmentIds === []) {
            return [];
        }

        return [...$this->completedSteps($fulfillmentIds), ...$this->unansweredQuestions($fulfillmentIds)];
    }

    /**
     * @param  list<string>  $fulfillmentIds
     * @return array<string, string>
     */
    private function completedSteps(array $fulfillmentIds): array
    {
        $notes = [];

        $events = FulfillmentEvent::query()
            ->whereIn('fulfillment_id', $fulfillmentIds)
            ->where('kind', FulfillmentEventKind::StepCompleted)
            ->inOrder()
            ->get();

        foreach ($events as $event) {
            $notes[$event->fulfillment_id] = $event->stepLabel();
        }

        return $notes;
    }

    /**
     * @param  list<string>  $fulfillmentIds
     * @return array<string, string>
     */
    private function unansweredQuestions(array $fulfillmentIds): array
    {
        $threads = Conversation::query()
            ->whereIn('fulfillment_id', $fulfillmentIds)
            ->pluck('fulfillment_id', 'id');

        if ($threads->isEmpty()) {
            return [];
        }

        $messages = Message::query()
            ->whereIn('conversation_id', $threads->keys()->all())
            ->where('sender_type', ActorType::Customer->value)
            ->whereNull('read_at')
            ->orderBy('sent_at')
            ->orderBy('id')
            ->get();

        $notes = [];

        foreach ($messages as $message) {
            $fulfillmentId = $threads->get($message->conversation_id);

            if (is_string($fulfillmentId)) {
                $notes[$fulfillmentId] = $message->body;
            }
        }

        return $notes;
    }
}
