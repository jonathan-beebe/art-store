module Fulfillments
  class ConfirmDelivered
    def initialize(roll_up_order_status: Orders::RollUpOrderStatus.new)
      @roll_up_order_status = roll_up_order_status
    end

    def call(fulfillment:, now:)
      status = Domain::Orders::FulfillmentStatus.transition(
        fulfillment.status, Domain::Orders::FulfillmentStatus::DELIVERED
      )

      fulfillment.transaction do
        fulfillment.update!(status: status, delivered_at: now)
        release_escrow(fulfillment, now)
        @roll_up_order_status.call(order: fulfillment.order)
      end

      fulfillment
    end

    private

    def release_escrow(fulfillment, now)
      release = Domain::Escrow::LedgerMovement.release(fulfillment.net)

      fulfillment.ledger_entries.create!(
        seller_id: fulfillment.seller_id,
        entry_type: release.entry_type,
        amount_cents: release.amount.cents,
        occurred_at: now
      )
    end
  end
end
