# The arithmetic a customer merge folds two carts and two favorites lists
# through: cart quantities summed per listing, clamped to what stock is left,
# a line that clamps to zero dropped; favorites de-duplicated by listing, the
# verified customer's own favorite kept over a duplicate. Pure math over plain
# data — no database access — so the fold's arithmetic is tested without a
# customer or a listing existing anywhere. `Customer#absorb` is what turns the
# answer into rows.
class CustomerMergePlan
  CartLine = Data.define(:listing_id, :quantity)

  # +verified+ and +anonymous+ are Hash(listing_id => quantity). +stock+ is
  # Hash(listing_id => units left); a listing absent from it contributes no
  # cap.
  def self.fold_cart_lines(verified, anonymous, stock)
    verified.merge(anonymous) { |_listing_id, from_verified, from_anonymous| from_verified + from_anonymous }
      .filter_map do |listing_id, quantity|
        cap = stock[listing_id]
        clamped = cap.nil? ? quantity : [ quantity, [ cap, 0 ].max ].min
        CartLine.new(listing_id: listing_id, quantity: clamped) if clamped.positive?
      end
  end

  # Anonymous favorites the verified customer does not already hold move;
  # ones that duplicate a favorite the verified customer already holds drop
  # instead of colliding with the unique index on (customer_id, listing_id).
  def self.partition_favorites(verified_listing_ids, anonymous_listing_ids)
    already_favorited = verified_listing_ids.to_set
    move, drop = anonymous_listing_ids.uniq.partition { |listing_id| already_favorited.exclude?(listing_id) }

    { move: move, drop: drop }
  end
end
