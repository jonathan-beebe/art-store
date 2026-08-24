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
  has_many :refunds, dependent: :destroy

  enum :status, {
    pending_verification: "pending_verification",
    awaiting_payment: "awaiting_payment",
    paid: "paid",
    payment_failed: "payment_failed",
    partially_shipped: "partially_shipped",
    shipped: "shipped",
    delivered: "delivered",
    cancelled: "cancelled",
    refunded: "refunded"
  }

  TRANSITIONS = {
    "pending_verification" => %w[awaiting_payment cancelled].freeze,
    "awaiting_payment" => %w[paid payment_failed cancelled].freeze,
    # A retry that is declined again leaves the order where it already was.
    "payment_failed" => %w[paid payment_failed cancelled].freeze,
    # Everything above `paid` is rolled up from the fulfillments rather than
    # asked for: `refunded` is where an order lands once every one of them has
    # been declined or refunded.
    "paid" => %w[partially_shipped shipped refunded].freeze,
    "partially_shipped" => %w[shipped refunded].freeze,
    "shipped" => %w[delivered refunded].freeze,
    "delivered" => %w[refunded].freeze,
    "cancelled" => [].freeze,
    "refunded" => [].freeze
  }.freeze

  # Placement takes the stock an order claims. These statuses hand it back, so
  # a listing that had sold out returns to the storefront.
  RELEASES_STOCK = %w[payment_failed cancelled].freeze

  # The statuses a customer, an admin, or the sweep may still call off. Past
  # them the path back is a refund.
  CANCELLABLE = %w[pending_verification awaiting_payment payment_failed].freeze

  # The statuses an order reaches only through an approved card, which is what
  # gives a refund something to reverse.
  CHARGED = %w[paid partially_shipped shipped delivered refunded].freeze

  scope :with_status, ->(status) { where(status: status) if status.present? }
  scope :for_customer, ->(customer_id) { where(customer_id: customer_id) if customer_id.present? }

  normalizes(*SHIPPING_FIELDS, with: ->(line) { line.strip.presence })

  validates :email, format: { with: EmailAddress::SHAPE }
  validates(*REQUIRED_SHIPPING_FIELDS, presence: true)

  # Cart or order lines that stopped a placement or charge from completing,
  # set only on a refusal `place`/`#pay!` returns without raising. Not a
  # database column.
  attr_writer :blocked_lines

  def blocked_lines
    @blocked_lines ||= []
  end

  # The cart becomes an order: every line keeps the title and price it was
  # bought at, the order splits into one fulfillment per seller with the
  # platform fee taken out, and the stock it claims leaves the storefront.
  # The transaction that takes the stock locks the listings the cart is about,
  # in id order, before the plan reads them, so a line another shopper
  # claimed first is refused rather than oversold. An order checkout left
  # incomplete or blocked comes back unsaved, so nothing moves.
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
      next refuse_incomplete(story, order) unless order.valid?

      plan = nil
      fulfillments = nil

      transaction do
        plan = OrderPlacement.plan(OrderPlacement.lock_listings(cart.items))
        raise ActiveRecord::Rollback unless plan.ok?

        order.save!
        snapshot(order, plan.items)
        fulfillments = split_by_seller(order, cart.subtotals_by_seller)
        cart.items.destroy_all
      end

      next refuse_unavailable(story, order, cart, plan) unless plan.ok?

      story.did("placed the order",
        order_id: order.id, total_cents: order.total_cents, status: order.status,
        fulfillment_ids: fulfillments.map(&:id))

      order
    end
  end

  # An incomplete address leaves the cart where it was. No line stopped it, so
  # the story carries none.
  private_class_method def self.refuse_incomplete(story, order)
    story.refused("the order is incomplete", blocked_lines: [])

    order
  end

  # A cart line that left the storefront or ran out leaves the cart where it
  # was. Every blocked line is named so the story — and the page the shopper
  # is on — say what to fix.
  private_class_method def self.refuse_unavailable(story, order, cart, plan)
    order.blocked_lines = plan.blocked_lines
    story.refused("a cart line is no longer available",
      cart_id: cart.id, blocked_lines: OrderPlacement.log_payload(plan.blocked_lines))

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
  # each seller's net in escrow and tells them their item sold. A retry that
  # reclaims stock locks its items' listings, in id order, before judging
  # each one fresh, so a listing that sold out while the order sat unpaid
  # refuses the charge instead of overselling.
  def pay!(card_number, at: Time.current)
    Story.tell("order.pay", "charging the card for the order",
      order_id: id, amount_cents: total_cents) do |story|
      card = FakeCard.new(card_number)
      landed = self.class.transition(status, card.approved? ? "paid" : "payment_failed")
      plan = nil
      payment = nil

      transaction do
        plan = restock_plan(landed)
        raise ActiveRecord::Rollback if plan && !plan.ok?

        move_stock(status, landed, plan)
        payment = record_attempt(card, at)
        update!(status: landed, finalized_at: (at if landed == "paid"))
        hold_in_escrow(at) if paid?
      end

      next refuse_stale(story, plan) if plan && !plan.ok?

      tell_the_charge(story, card, payment)

      self
    end
  end

  # The order is called off before any money changed hands: the stock it was
  # holding goes back on the storefront and nothing can charge it afterwards.
  # The row is locked and judged inside the transaction that writes, so two
  # cancels racing each other leave one refusal behind.
  def cancel!(by:)
    Story.tell("order.cancel", "cancelling the order", order_id: id, status_from: status) do |story|
      from = status

      transaction do
        lock!
        landed = self.class.transition(status, "cancelled")
        move_stock(status, landed, nil)
        update!(status: landed)
      end

      Notification.order_cancelled(self) if by.is_a?(Admin)
      story.did("cancelled the order", order_id: id, status_from: from, status_to: status)

      self
    end
  end

  # Guest orders that were never verified hold their stock off the storefront.
  # This hands it back for every order left sitting past the cutoff, in one
  # transaction over rows it locks, and finds nothing to do on a second run.
  def self.sweep_stale(before:)
    # Nobody asked for this one: the lines it writes name the system rather
    # than whoever was last on the page.
    Current.set(actor_type: "system", actor_id: nil) do
      Story.tell("order.sweep", "cancelling the orders nobody verified",
        before: before.utc.iso8601) do |story|
        cancelled = transaction do
          pending_verification.where(placed_at: ...before).order(:id).lock.map do |order|
            story.doing("cancelling a stale order", order_id: order.id, placed_at: order.placed_at.utc.iso8601)
            order.cancel!(by: :system)
          end
        end

        story.did("cancelled the orders nobody verified",
          before: before.utc.iso8601, order_count: cancelled.size)

        cancelled
      end
    end
  end

  # The cutoff the sweep runs against when nobody names one.
  def self.stale_before(at: Time.current)
    at - Rails.configuration.x.orders.stale_hours.hours
  end

  def roll_up_status!
    # Reloaded because the caller reached the order through a fulfillment it
    # has already changed, and the cached collection still holds the old row.
    update!(status: rolled_up_status(fulfillments.reload))

    self
  end

  def cancellable?
    CANCELLABLE.include?(status)
  end

  def charged?
    CHARGED.include?(status)
  end

  # The charge a refund reverses. Only one card attempt is ever approved, so
  # there is at most one.
  def approved_payment
    payments.approved.order(:processed_at, :id).last
  end

  def total
    Money.from_cents(total_cents)
  end

  def refunded
    Money.from_cents(refunded_cents)
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

  # The plan a retry must clear before it reclaims stock — nil when this
  # charge does not move stock at all, or moves it back to the storefront
  # rather than claiming it. Read fresh each call, so a listing another buyer
  # took while this order sat unpaid is judged as it stands now.
  def restock_plan(to)
    return nil unless holds_stock?(status) != holds_stock?(to) && holds_stock?(to)

    OrderPlacement.plan(OrderPlacement.lock_listings(items))
  end

  def move_stock(from, to, plan)
    return if holds_stock?(from) == holds_stock?(to)

    if holds_stock?(to)
      plan.items.each { |item| item.listing.take_stock!(item.quantity) }
    else
      items.includes(:listing).each { |item| item.listing.restore_stock!(item.quantity) }
    end
  end

  def holds_stock?(status)
    RELEASES_STOCK.exclude?(status)
  end

  # A retry that reclaims stock leaves the order where it was. Every blocked
  # line is named so the story — and the pay page — say what changed.
  def refuse_stale(story, plan)
    self.blocked_lines = plan.blocked_lines
    story.refused("a cart line is no longer available",
      order_id: id, blocked_lines: OrderPlacement.log_payload(plan.blocked_lines))

    self
  end

  def hold_in_escrow(at)
    fulfillments.each do |fulfillment|
      LedgerEntry.hold(fulfillment, at: at)

      Notification.item_sold(fulfillment)
    end
  end

  # An order that spans sellers reads from its fulfillments: a delivered one
  # mixed with an unshipped one is still partially shipped. A fulfillment
  # whose money went back is out of the count, so a shipped piece beside a
  # declined one still reads as shipped — and an order every seller pulled out
  # of reads as refunded.
  def rolled_up_status(fulfillments)
    raise ArgumentError, "an order rolls up from at least one fulfillment" if fulfillments.empty?

    live = fulfillments.reject(&:reversed?)
    return "refunded" if live.empty?
    return "delivered" if live.all?(&:delivered?)
    return "shipped" if live.all?(&:departed?)
    return "partially_shipped" if live.any?(&:departed?)

    "paid"
  end
end
