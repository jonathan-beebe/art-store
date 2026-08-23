class CartItem < ApplicationRecord
  belongs_to :cart
  belongs_to :listing

  def to_line
    Domain::Cart::CartLine.new(
      seller_id: listing.seller_id, unit_price: Domain::Money.from_cents(listing.price_cents), quantity: quantity
    )
  end
end
