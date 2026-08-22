class LedgerEntry < ApplicationRecord
  belongs_to :seller
  belongs_to :fulfillment, optional: true
  belongs_to :payout, optional: true

  enum :entry_type, Domain::Escrow::LedgerEntryType::ALL.index_by(&:to_sym)

  scope :occurred_by, ->(moment) { where(occurred_at: ..moment) }

  def to_movement
    Domain::Escrow::LedgerMovement.new(entry_type: entry_type, amount: Domain::Money.from_cents(amount_cents))
  end
end
