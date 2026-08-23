class CartItem < ApplicationRecord
  belongs_to :cart
  belongs_to :listing

  validates :quantity, numericality: { only_integer: true, greater_than_or_equal_to: 1 }

  def total
    Money.from_cents(listing.price_cents) * quantity
  end
end
