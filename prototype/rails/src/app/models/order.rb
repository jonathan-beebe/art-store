class Order < ApplicationRecord
  prefixed_id :ord

  include EmailAddress

  # What checkout collects, under the column names the form posts.
  SHIPPING_FIELDS = %i[
    shipping_name shipping_line1 shipping_line2 shipping_city shipping_region
    shipping_postal_code shipping_country
  ].freeze

  # A second address line is the one part a package can arrive without.
  REQUIRED_SHIPPING_FIELDS = (SHIPPING_FIELDS - %i[shipping_line2]).freeze

  belongs_to :customer
  has_many :items, class_name: "OrderItem", dependent: :destroy, inverse_of: :order
  has_many :fulfillments, dependent: :destroy
  has_many :payments, dependent: :destroy

  enum :status, {
    pending_verification: "pending_verification",
    awaiting_payment: "awaiting_payment",
    paid: "paid",
    payment_failed: "payment_failed",
    partially_shipped: "partially_shipped",
    shipped: "shipped",
    delivered: "delivered",
    cancelled: "cancelled"
  }

  TRANSITIONS = {
    "pending_verification" => %w[awaiting_payment cancelled].freeze,
    "awaiting_payment" => %w[paid payment_failed cancelled].freeze,
    # A retry that is declined again leaves the order where it already was.
    "payment_failed" => %w[paid payment_failed cancelled].freeze,
    "paid" => %w[partially_shipped shipped].freeze,
    "partially_shipped" => %w[shipped].freeze,
    "shipped" => %w[delivered].freeze,
    "delivered" => [].freeze,
    "cancelled" => [].freeze
  }.freeze

  # Placement takes the stock an order claims. These statuses hand it back, so
  # a listing that had sold out returns to the storefront.
  RELEASES_STOCK = %w[payment_failed cancelled].freeze

  normalizes(*SHIPPING_FIELDS, with: ->(line) { line.strip.presence })

  validates :email, format: { with: EmailAddress::SHAPE }
  validates(*REQUIRED_SHIPPING_FIELDS, presence: true)

  # The cart becomes an order: every line keeps the title and price it was
  # bought at, the order splits into one fulfillment per seller with the
  # platform fee taken out, and the stock it claims leaves the storefront.
  # An order checkout left incomplete comes back unsaved, so nothing moves.
  def self.place(cart:, customer:, email:, shipping:, email_verified: false, at: Time.current)
    raise ArgumentError, "an order needs at least one item" if cart.empty?

    Story.tell("order.place", "placing an order from the cart",
      cart_id: cart.id, line_count: cart.items.size) do |story|
      order = new(
        **shipping,
        customer: customer, email: email, placed_at: at,
        status: email_verified ? :awaiting_payment : :pending_verification,
        subtotal_cents: cart.subtotal.cents, total_cents: cart.subtotal.cents
      )
      next refuse(story, cart, order) unless order.valid?

      fulfillments = nil

      transaction do
        order.save!
        snapshot(order, cart.items.includes(:listing).to_a)
        fulfillments = split_by_seller(order, cart.subtotals_by_seller)
        cart.items.destroy_all
      end

      story.did("placed the order",
        order_id: order.id, total_cents: order.total_cents, status: order.status,
        fulfillment_ids: fulfillments.map(&:id))

      order
    end
  end

  # A checkout that cannot be placed leaves the cart where it was. The lines
  # that stopped it are named so the story says what to fix.
  private_class_method def self.refuse(story, cart, order)
    story.refused("the order is incomplete", cart_id: cart.id, blocked_lines: [])

    order
  end

  private_class_method def self.snapshot(order, items)
    items.each do |item|
      listing = item.listing

      order.items.create!(
        listing: listing, seller_id: listing.seller_id, title: listing.title,
        unit_price_cents: listing.price_cents, quantity: item.quantity
      )
      listing.take_stock!(item.quantity)
    end
  end

  private_class_method def self.split_by_seller(order, subtotals)
    subtotals.map do |seller_id, subtotal|
      order.fulfillments.create!(
        seller_id: seller_id,
        subtotal_cents: subtotal.cents,
        fee_cents: Fulfillment.fee_for(subtotal).cents,
        net_cents: Fulfillment.net_for(subtotal).cents
      )
    end
  end

  # The lifecycle move as a value, for a caller that works out a status without
  # a record to write it to.
  def self.transition(from, to)
    raise TransitionError, "An order cannot move from #{from} to #{to}." unless
      TRANSITIONS.fetch(from, []).include?(to)

    to
  end

  def transition_to!(status)
    update!(status: self.class.transition(self.status, status))
  end

  def next_statuses
    TRANSITIONS.fetch(status, [])
  end

  # An order awaits a card for as long as one could still carry it to paid,
  # which is what the storefront asks before it shows a card form.
  def awaits_card?
    next_statuses.include?("paid")
  end

  # A guest's order is unpaid before it is chargeable: verifying the address is
  # the step between.
  def unpaid?
    awaits_card? || next_statuses.include?("awaiting_payment")
  end

  def payable_by?(customer_signed_in)
    customer_signed_in && awaits_card?
  end

  # Verifying an email is what lets a guest's order reach the card form.
  def mark_awaiting_payment!
    transition_to!("awaiting_payment") if pending_verification?

    self
  end

  # One charge attempt. The card decides where the order lands, a payments row
  # keeps what was tried, and the stock follows the status. A paid order holds
  # each seller's net in escrow and tells them their item sold.
  def pay!(card_number, at: Time.current)
    Story.tell("order.pay", "charging the card for the order",
      order_id: id, amount_cents: total_cents) do |story|
      card = FakeCard.new(card_number)
      landed = self.class.transition(status, card.approved? ? "paid" : "payment_failed")
      payment = nil

      transaction do
        move_stock(status, landed)
        payment = record_attempt(card, at)
        update!(status: landed, finalized_at: (at if landed == "paid"))
        hold_in_escrow(at) if paid?
      end

      tell_the_charge(story, card, payment)

      self
    end
  end

  def roll_up_status!
    # Reloaded because the caller reached the order through a fulfillment it
    # has already changed, and the cached collection still holds the old row.
    update!(status: rolled_up_status(fulfillments.reload))

    self
  end

  def total
    Money.from_cents(total_cents)
  end

  private

  # An approved card moves the order on; a declined one leaves it where it was
  # and says which card table row turned it down.
  def tell_the_charge(story, card, payment)
    return story.did("the card was approved", order_id: id, payment_id: payment.id, status: status) if card.approved?

    story.refused("the card was declined",
      order_id: id, payment_id: payment.id, status: status, decline_reason: card.decline_reason)
  end

  def record_attempt(card, at)
    payments.create!(
      status: card.approved? ? :approved : :declined,
      amount_cents: total_cents,
      card_last_four: card.last_four,
      decline_reason: card.decline_reason,
      processed_at: at
    )
  end

  def move_stock(from, to)
    return if holds_stock?(from) == holds_stock?(to)

    items.includes(:listing).each do |item|
      holds_stock?(to) ? item.listing.take_stock!(item.quantity) : item.listing.restore_stock!(item.quantity)
    end
  end

  def holds_stock?(status)
    RELEASES_STOCK.exclude?(status)
  end

  def hold_in_escrow(at)
    fulfillments.each do |fulfillment|
      LedgerEntry.hold(fulfillment, at: at)

      Notification.item_sold(fulfillment)
    end
  end

  # An order that spans sellers reads from its fulfillments: a delivered one
  # mixed with an unshipped one is still partially shipped.
  def rolled_up_status(fulfillments)
    raise ArgumentError, "an order rolls up from at least one fulfillment" if fulfillments.empty?
    return "delivered" if fulfillments.all?(&:delivered?)
    return "shipped" if fulfillments.all?(&:departed?)
    return "partially_shipped" if fulfillments.any?(&:departed?)

    "paid"
  end
end
