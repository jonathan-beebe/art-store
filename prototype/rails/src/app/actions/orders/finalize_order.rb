module Orders
  class FinalizeOrder
    def initialize(notify: Notifications::Notify.new)
      @notify = notify
    end

    def call(order:, card_number:, now:)
      attempt = Domain::Orders::PaymentAttempt.from(
        status: order.status,
        decision: Domain::Payments::FakeCard.decide(card_number),
        now: now
      )

      order.transaction do
        move_stock(order, attempt.stock_change)
        record_payment(order, attempt, now)
        order.update!(status: attempt.order_status, finalized_at: attempt.finalized_at)
        attempt.settled(order.fulfillments.to_a).each { |fulfillment| settle(order, fulfillment, now) }
      end

      order
    end

    private

    def move_stock(order, change)
      order.items.includes(:listing).each do |item|
        listing = item.listing
        stock = Domain::Listings::ListingStock.after(
          change, quantity: listing.quantity, status: listing.status, items: item.quantity
        )
        listing.update!(quantity: stock.quantity, status: stock.status)
      end
    end

    def record_payment(order, attempt, now)
      order.payments.create!(
        status: attempt.payment_status,
        amount_cents: order.total_cents,
        card_last_four: attempt.card_last_four,
        decline_reason: attempt.decline_reason,
        processed_at: now
      )
    end

    def settle(order, fulfillment, now)
      hold = Domain::Escrow::LedgerMovement.hold(fulfillment.net)

      fulfillment.ledger_entries.create!(
        seller_id: fulfillment.seller_id,
        entry_type: hold.entry_type,
        amount_cents: hold.amount.cents,
        occurred_at: now
      )

      @notify.call(
        recipient_type: Domain::Notifications::RecipientType::SELLER,
        recipient_id: fulfillment.seller_id,
        message: Domain::Notifications::NotificationMessage.item_sold(order.id, fulfillment.net)
      )
    end
  end
end
