class Refund < ApplicationRecord
  prefixed_id :rfd

  REASON_LIMIT = 500
  MISSING_REASON = "A refund needs a reason.".freeze
  LONG_REASON = "Keep the reason under #{REASON_LIMIT} characters.".freeze

  belongs_to :order
  belongs_to :fulfillment
  belongs_to :payment

  # A seller declines, an admin refunds. The pair names the actor without a
  # polymorphic association, because the column holds the actor's name rather
  # than their class.
  enum :issued_by_type, { seller: "seller", admin: "admin" }, prefix: :issued_by

  normalizes :reason, with: ->(reason) { reason.strip.presence }

  validate :reason_says_something

  # The money for one fulfillment goes back: a row saying who sent it and why,
  # the order's running total, and the ledger entry that takes it off the
  # seller. Called inside the transaction that moves the fulfillment.
  def self.issue(fulfillment, reason:, by:, at:)
    order = fulfillment.order

    refund = create!(
      order: order, fulfillment: fulfillment, payment: order.approved_payment,
      amount_cents: fulfillment.subtotal_cents, reason: reason,
      issued_by_type: by.model_name.singular, issued_by_id: by.id, created_at: at
    )
    order.update!(refunded_cents: order.refunded_cents + refund.amount_cents)
    LedgerEntry.refund(fulfillment, at: at)

    refund
  end

  def amount
    Money.from_cents(amount_cents)
  end

  private

  # Whoever sent the money back has to say why: the sentence is what the other
  # side reads on their order page, so it is one refusal, not a field error.
  def reason_says_something
    return errors.add(:base, MISSING_REASON) if reason.blank?

    errors.add(:base, LONG_REASON) if reason.length > REASON_LIMIT
  end
end
