class Cart < ApplicationRecord
  prefixed_id :crt

  belongs_to :customer
  has_many :items, class_name: "CartItem", dependent: :destroy, inverse_of: :cart

  # A cart never holds more of a listing than the seller has left. Adding the
  # same listing twice adds to the line it already has.
  def add(listing, quantity: 1, at: Time.current)
    item = items.find_or_initialize_by(listing: listing)
    event = item.persisted? ? "cart.update" : "cart.add"

    Story.tell(event, "putting #{quantity} of a listing in the cart",
      cart_id: id, listing_id: listing.id, quantity: quantity) do |story|
      item.update!(quantity: within_stock(item.quantity.to_i + quantity, listing))
      listing.record_event!("cart_add", customer_id: customer_id, at: at)

      story.did("the cart holds #{item.quantity} of the listing",
        cart_id: id, listing_id: listing.id, quantity: item.quantity)

      item
    end
  end

  def remove(listing)
    Story.tell("cart.remove", "taking a listing out of the cart",
      cart_id: id, listing_id: listing.id) do |story|
      removed = items.where(listing: listing).destroy_all

      story.did("took the listing out of the cart",
        cart_id: id, listing_id: listing.id, line_count: removed.size)

      self
    end
  end

  def empty?
    items.empty?
  end

  def item_count
    items.sum(:quantity)
  end

  # A line the customer cannot actually be charged for — removed, off sale,
  # sold out, or short of the quantity held — is left out: the number shown
  # here is always one the cart could still be placed for.
  def subtotal
    total_of(available_items)
  end

  # What each seller in the cart is owed, ordered by seller id so an order
  # splits the same way twice. Blocked lines are left out for the same reason
  # `subtotal` leaves them out.
  def subtotals_by_seller
    available_items
      .group_by { |item| item.listing.seller_id }
      .transform_values { |own| total_of(own) }
      .sort.to_h
  end

  private

  def available_items
    loaded = items.includes(:listing).to_a
    blocked_listing_ids = OrderPlacement.plan(loaded).blocked_lines.map(&:listing_id).to_set

    loaded.reject { |item| blocked_listing_ids.include?(item.listing_id) }
  end

  def within_stock(requested, listing)
    raise ArgumentError, "a cart holds at least one of a listing, got #{requested}" if requested < 1
    raise ArgumentError, "that listing is sold out" if listing.quantity < 1

    [ requested, listing.quantity ].min
  end

  def total_of(items)
    items.sum(Money.from_cents(0), &:total)
  end
end
