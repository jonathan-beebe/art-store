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

  # The standings the admin directory narrows the table to. `all` is the
  # filter a page carries when nobody has chosen one.
  STANDINGS = %w[all verified anonymous blocked].freeze

  # One line of the customers directory: a customer beside the counts the
  # table shows for them. `blocked` is read once for the whole table rather
  # than through `Customer#blocked?`, which would cost a query per row.
  Row = Data.define(:customer, :order_count, :favorite_count, :cart_line_count, :blocked) do
    delegate :id, :email, :display_name, :anonymous?, to: :customer
    alias_method :blocked?, :blocked
  end

  # A merge from this customer's side: the visitor folded into them, or the
  # account they were folded into.
  Merge = Data.define(:direction, :other, :created_at)

  has_many :merges_absorbed, class_name: "CustomerMerge", dependent: :destroy, inverse_of: :customer
  has_one :merge_into, class_name: "CustomerMerge", foreign_key: :anonymous_customer_id,
    inverse_of: :anonymous_customer
  has_many :carts, dependent: :destroy
  has_many :favorites, dependent: :destroy
  has_many :listing_events, dependent: :nullify
  has_many :notifications, as: :recipient, dependent: :destroy
  has_many :orders, dependent: :restrict_with_error
  has_many :blocks, -> { order(:created_at, :id) }, class_name: "CustomerBlock", dependent: :destroy

  scope :verified, -> { where.not(email: nil) }
  scope :anonymous, -> { where(email: nil) }
  # An admin's block takes shopping and messaging away from a customer.
  scope :blocked, -> { where(id: CustomerBlock.active.select(:customer_id)) }
  scope :standing, ->(standing) {
    case standing
    when "verified" then verified
    when "anonymous" then anonymous
    when "blocked" then blocked
    end
  }

  # Every customer in the current scope with the counts the directory shows.
  # Each count is one grouped read for the whole table.
  def self.directory
    order_counts = Order.group(:customer_id).count
    favorite_counts = Favorite.group(:customer_id).count
    cart_line_counts = CartItem.joins(:cart).group("carts.customer_id").count
    blocked_ids = CustomerBlock.active.distinct.pluck(:customer_id).to_set

    order(:created_at, :id).map do |customer|
      Row.new(
        customer: customer,
        order_count: order_counts.fetch(customer.id, 0),
        favorite_count: favorite_counts.fetch(customer.id, 0),
        cart_line_count: cart_line_counts.fetch(customer.id, 0),
        blocked: blocked_ids.include?(customer.id)
      )
    end
  end

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

  # The block standing over this customer right now, if any. Both `block!`
  # and `lift_block!` key off this rather than a block id, so a page that
  # knows the customer needs nothing else. Reads over `blocks` rather than
  # the `active` scope, so a caller that already loaded the association (the
  # admin customer page) pays no query per call.
  def active_block
    blocks.detect(&:active?)
  end

  # Whether an admin has taken shopping and messaging away from this customer.
  def blocked?
    active_block.present?
  end

  # What a blocked customer reads for why, or nil in good standing.
  def blocked_reason
    active_block&.reason
  end

  # The one predicate a block turns off: adding to a cart, checking out,
  # paying, and posting a message. Browsing, favorites, and reading threads
  # stay open, which is why this is named for what shopping needs rather than
  # after the block itself.
  def can_shop?
    !blocked?
  end

  # Stops the customer buying and messaging. At most one block is active at a
  # time.
  def block!(reason:, by:)
    Story.tell("moderation.block_customer", "blocking the customer",
      customer_id: id) do |story|
      raise TransitionError, "customer #{id} is already blocked" if blocked?

      block = blocks.create!(reason: reason, admin: by)

      story.did("blocked the customer", customer_id: id, block_id: block.id)

      block
    end
  end

  # Hands a blocked customer their cart, checkout, and messages back.
  def lift_block!
    Story.tell("moderation.lift_customer_block", "lifting the block", customer_id: id) do |story|
      block = active_block
      raise TransitionError, "customer #{id} is not blocked" if block.nil?

      block.update!(lifted_at: Time.current)

      story.did("lifted the block", customer_id: id, block_id: block.id)

      block
    end
  end

  # Every merge this customer was named in, oldest first, whichever side of it
  # they were on.
  def merges
    history = merges_absorbed.includes(:anonymous_customer).map do |merge|
      Merge.new(direction: "absorbed", other: merge.anonymous_customer, created_at: merge.created_at)
    end
    if merge_into
      history << Merge.new(direction: "folded_into", other: merge_into.customer, created_at: merge_into.created_at)
    end

    history.sort_by(&:created_at)
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
    Story.tell("customer.merge", "folding an anonymous visitor into the account",
      customer_id: id, anonymous_customer_id: anonymous.id) do |story|
      fold(anonymous)

      story.did("folded the anonymous visitor into the account",
        customer_id: id, anonymous_customer_id: anonymous.id)

      self
    end
  end

  private

  def fold(anonymous)
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
  end
end
