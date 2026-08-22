class Payout < ApplicationRecord
  belongs_to :seller
  has_many :ledger_entries, dependent: :nullify

  def amount
    Domain::Money.from_cents(amount_cents)
  end
end
