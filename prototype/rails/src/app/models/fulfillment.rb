class Fulfillment < ApplicationRecord
  belongs_to :order
  belongs_to :seller
  has_many :ledger_entries, dependent: :destroy

  enum :status, Domain::Orders::FulfillmentStatus::ALL.index_by(&:to_sym)

  def net
    Domain::Money.from_cents(net_cents)
  end
end
