module Fulfillments
  class ConfirmDelivered
    def call(fulfillment:, now:)
      status = Domain::Orders::FulfillmentStatus.transition(
        fulfillment.status, Domain::Orders::FulfillmentStatus::DELIVERED
      )

      fulfillment.transaction do
        fulfillment.update!(status: status, delivered_at: now)
        release_escrow(fulfillment, now)
        fulfillment.order.roll_up_status!
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
