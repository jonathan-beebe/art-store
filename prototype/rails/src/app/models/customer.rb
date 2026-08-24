class Customer < ApplicationRecord
  prefixed_id :cus

  include EmailAddress
  include Messaging

  # The rows that move with a customer when an anonymous identity is absorbed
  # into a verified one, each by re-pointing the column it hangs from.
  # Conversations move through `Conversation#move_to`, since two threads of the
  # same shape have to fold into one.
  MERGED_ASSOCIATIONS = %i[
    favorites carts orders listing_events notifications sent_messages
  ].freeze

  has_many :merges_absorbed, class_name: "CustomerMerge", dependent: :destroy, inverse_of: :customer
  has_many :carts, dependent: :destroy
  has_many :favorites, dependent: :destroy
  has_many :listing_events, dependent: :nullify
  has_many :notifications, as: :recipient, dependent: :destroy
  has_many :orders, dependent: :restrict_with_error

  scope :verified, -> { where.not(email: nil) }

  # The customer that owns the address once a link for it is followed:
  # a new account, the account already holding it, the anonymous row the
  # identity cookie points at, or the account that row is absorbed into.
  def self.claim(email, current: nil)
    anonymous = current if current&.anonymous?
    owner = find_by(email: email)

    return create!(email: email, email_verified_at: Time.current) if owner.nil? && anonymous.nil?
    return anonymous.claim_address(email) if owner.nil?

    owner.verify!
    anonymous ? owner.absorb(anonymous) : owner
  end

  # Returns nil when the cookie is absent, carries anything but a customer id,
  # or points at a customer that no longer exists.
  def self.from_cookie(value)
    id = PrefixedUlid.parse(value, :cus)
    return nil if id.nil?

    find_by(id: CustomerMerge.where(anonymous_customer_id: id).pick(:customer_id) || id)
  end

  def anonymous?
    email.nil?
  end

  # A customer gives an address before they give a name, and a visitor gives
  # neither, so the display falls back through what is there.
  def display_name
    name.to_s.strip.presence || email.to_s.split("@").first.presence || "Visitor ##{id}"
  end

  # A merge hands the verified customer whatever cart the anonymous visitor was
  # filling, so one customer can own two. The one holding the most items is the
  # one they were shopping with.
  def current_cart
    carts.includes(:items).max_by { |cart| [ cart.items.size, cart.id ] } || carts.create!
  end

  # One button favorites and unfavorites, so what it does follows from what the
  # visitor has saved already. Returns :added or :removed.
  def toggle_favorite(listing, at: Time.current)
    saved = favorites.find_by(listing: listing)

    if saved
      saved.destroy!
      listing.record_event!("unfavorite", customer_id: id, at: at)

      return :removed
    end

    favorites.create!(listing: listing)
    listing.record_event!("favorite", customer_id: id, at: at)

    :added
  end

  def favorited?(listing)
    favorites.exists?(listing: listing)
  end

  def claim_address(email)
    update!(email: email, email_verified_at: Time.current)

    self
  end

  # A guest checkout can leave an address on a customer without verifying it;
  # clicking a link for that address settles it.
  def verify!
    update!(email_verified_at: Time.current) if email_verified_at.nil?

    self
  end

  # Takes over the history of an anonymous customer and returns self. The
  # anonymous row survives so a cookie still holding its id resolves forward
  # instead of starting the visitor over.
  def absorb(anonymous)
    transaction do
      MERGED_ASSOCIATIONS.each do |association|
        # Each association names the column it points back through: notifications
        # arrive at a polymorphic `recipient_id`, the rest at `customer_id`.
        foreign_key = self.class.reflect_on_association(association).foreign_key
        anonymous.public_send(association).update_all(foreign_key => id)
      end

      anonymous.conversations.each { |conversation| conversation.move_to(self) }
      merges_absorbed.create!(anonymous_customer: anonymous)
    end

    self
  end
end
