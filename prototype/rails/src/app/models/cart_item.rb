class CartItem < ApplicationRecord
  belongs_to :cart
  belongs_to :listing

  def to_line
    Domain::Cart::CartLine.new(seller_id: listing.seller_id, unit_price: listing.price, quantity: quantity)
  end
end
