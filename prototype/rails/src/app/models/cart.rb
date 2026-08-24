class Cart < ApplicationRecord
  prefixed_id :crt

  belongs_to :customer
  has_many :items, class_name: "CartItem", dependent: :destroy, inverse_of: :cart

  # A cart never holds more of a listing than the seller has left. Adding the
  # same listing twice adds to the line it already has.
  def add(listing, quantity: 1, at: Time.current)
    item = items.find_or_initialize_by(listing: listing)
    item.update!(quantity: within_stock(item.quantity.to_i + quantity, listing))

    listing.record_event!("cart_add", customer_id: customer_id, at: at)

    item
  end

  def remove(listing)
    items.where(listing: listing).destroy_all

    self
  end

  def empty?
    items.empty?
  end

  def item_count
    items.sum(:quantity)
  end

  def subtotal
    total_of(items.includes(:listing))
  end

  # What each seller in the cart is owed, ordered by seller id so an order
  # splits the same way twice.
  def subtotals_by_seller
    items.includes(:listing)
      .group_by { |item| item.listing.seller_id }
      .transform_values { |own| total_of(own) }
      .sort.to_h
  end

  private

  def within_stock(requested, listing)
    raise ArgumentError, "a cart holds at least one of a listing, got #{requested}" if requested < 1
    raise ArgumentError, "that listing is sold out" if listing.quantity < 1

    [ requested, listing.quantity ].min
  end

  def total_of(items)
    items.sum(Money.from_cents(0), &:total)
  end
end
