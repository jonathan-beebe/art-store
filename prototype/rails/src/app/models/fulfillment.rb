class Fulfillment < ApplicationRecord
  prefixed_id :ful

  belongs_to :order
  belongs_to :seller
  has_many :ledger_entries, dependent: :destroy
  has_many :refunds, dependent: :destroy
  has_many :conversations, as: :subject, dependent: :destroy

  enum :status, {
    awaiting_shipment: "awaiting_shipment",
    shipped: "shipped",
    delivered: "delivered",
    declined: "declined",
    refunded: "refunded"
  }

  TRANSITIONS = {
    "awaiting_shipment" => %w[shipped declined refunded].freeze,
    "shipped" => %w[delivered refunded].freeze,
    "delivered" => %w[refunded].freeze,
    "declined" => [].freeze,
    "refunded" => [].freeze
  }.freeze

  # A fulfillment the customer is waiting on rather than the seller.
  DEPARTED = %w[shipped delivered].freeze

  # A fulfillment whose money has gone back. The order rolls up from the rest,
  # and the platform forgoes the fee on these.
  REVERSED = %w[declined refunded].freeze

  # Only a charged order has money to send back, and the refund row names the
  # payment it reverses.
  UNCHARGED = "A fulfillment on an order nobody has paid for cannot be refunded.".freeze

  # The platform takes a tenth of every sale off the top; the seller nets the
  # rest, and that net is what moves through escrow.
  PLATFORM_FEE_PERCENT = 10

  # A shipment the customer can follow needs both parts, and the seller's form
  # asks for the pair, so the refusal is one sentence.
  MISSING_DETAILS = "A shipment needs a carrier and a tracking number.".freeze

  scope :with_status, ->(status) { where(status: status) if status.present? }
  scope :for_seller, ->(seller_id) { where(seller_id: seller_id) if seller_id.present? }
  # The sales that stand, and the ones whose money went back. A fulfillment
  # the escrow never held — an order nobody paid for — is in neither.
  scope :settled, -> { where(id: LedgerEntry.held.select(:fulfillment_id)) }
  scope :live, -> { where.not(status: REVERSED) }
  scope :reversed, -> { where(status: REVERSED) }

  normalizes :carrier, :tracking_number, with: ->(field) { field.strip.presence }

  validate :shipment_is_trackable, on: :ship
  validate(on: :ship) { must_move_to("shipped") }
  validate(on: :deliver) { must_move_to("delivered") }
  validate(on: :declined) { must_move_to("declined") }
  validate(on: :declined) { must_be_charged }
  validate(on: :refunded) { must_move_to("refunded") }
  validate(on: :refunded) { must_be_charged }

  # The platform's cut of the sales that stand. A refunded fulfillment's fee
  # is forgone rather than kept, so accounting reads the two apart.
  def self.fees_earned_cents
    settled.live.sum(:fee_cents)
  end

  def self.fees_refunded_cents
    settled.reversed.sum(:fee_cents)
  end

  def self.fee_for(subtotal)
    subtotal.percent(PLATFORM_FEE_PERCENT)
  end

  def self.net_for(subtotal)
    Money.from_cents(subtotal.cents - fee_for(subtotal).cents)
  end

  # The seller hands the package over: the fulfillment records how to follow
  # it, the order catches up, and the customer is told where to look.
  def ship!(carrier:, tracking_number:, at: Time.current)
    Story.tell("fulfillment.ship", "handing the package to the carrier",
      fulfillment_id: id, order_id: order_id, status_from: status) do |story|
      assign_attributes(carrier: carrier, tracking_number: tracking_number)
      validate!(:ship)

      transaction do
        update!(status: :shipped, shipped_at: at)
        order.roll_up_status!
        Notification.order_shipped(self)
      end

      story.did("the package is with the carrier",
        fulfillment_id: id, order_id: order_id, status_to: status, order_status: order.status)

      self
    end
  end

  # The customer confirms the package arrived, which is what releases the money
  # the sale has been holding for the seller.
  def deliver!(at: Time.current)
    Story.tell("fulfillment.deliver", "confirming the package arrived",
      fulfillment_id: id, order_id: order_id, status_from: status) do |story|
      validate!(:deliver)

      transaction do
        update!(status: :delivered, delivered_at: at)
        LedgerEntry.release(self, at: at)
        order.roll_up_status!
      end

      story.did("the package arrived",
        fulfillment_id: id, order_id: order_id, status_to: status, order_status: order.status)

      self
    end
  end

  # The seller cannot ship what they sold: the fulfillment stops, the stock it
  # claimed goes back on the storefront, and the customer's money goes back.
  def decline!(reason:, by:, at: Time.current)
    Story.tell("fulfillment.decline", "declining the fulfillment the seller cannot ship",
      fulfillment_id: id, order_id: order_id, status_from: status) do |story|
      refund = reverse_to!(:declined, reason: reason, by: by, at: at) do |issued|
        restore_stock
        Notification.fulfillment_declined(self, issued)
      end

      story.did("the seller declined the fulfillment", fulfillment_id: id, order_id: order_id,
        status_to: status, order_status: order.status, refund_id: refund.id)

      self
    end
  end

  # The platform sends the money back over the seller's head — a dispute the
  # customer won, or a seller who never answered. The stock stays sold: the
  # piece is with the customer, or nobody knows where it is.
  def refund!(reason:, by:, at: Time.current)
    reverse_to!(:refunded, reason: reason, by: by, at: at) do |issued|
      Notification.fulfillment_refunded(self, issued)
    end

    self
  end

  # An order may span sellers. These are the lines of it this fulfillment
  # ships.
  def items
    order.items.select { |item| item.seller_id == seller_id }
  end

  def can_transition_to?(status)
    TRANSITIONS.fetch(self.status, []).include?(status.to_s)
  end

  def departed?
    DEPARTED.include?(status)
  end

  # Whether this fulfillment's money has gone back, which is what takes it out
  # of the order's roll-up.
  def reversed?
    REVERSED.include?(status)
  end

  # Whether the seller can still pull out of the sale, and whether the platform
  # can still send the money back over their head. Both need a card that was
  # approved: there is nothing to reverse before one.
  def declinable?
    can_transition_to?(:declined) && order.charged?
  end

  def refundable?
    can_transition_to?(:refunded) && order.charged?
  end

  # What the customer was told when the money went back, for the pages that
  # show a refunded fulfillment.
  def refund_reason
    refunds.first&.reason
  end

  def subtotal
    Money.from_cents(subtotal_cents)
  end

  def fee
    Money.from_cents(fee_cents)
  end

  def net
    Money.from_cents(net_cents)
  end

  private

  # The one move that sends money back, whichever side asked for it. The row
  # is locked and judged inside the transaction that writes, so a second
  # decline or refund racing the first is refused rather than paid twice.
  def reverse_to!(status, reason:, by:, at:)
    Story.tell("refund.issue", "sending the fulfillment's money back to the customer",
      fulfillment_id: id, order_id: order_id, amount_cents: subtotal_cents) do |story|
      refund = nil

      transaction do
        lock!
        validate!(status)
        update!(status: status)
        refund = Refund.issue(self, reason: reason, by: by, at: at)
        yield refund
        order.roll_up_status!
      end

      story.did("sent the fulfillment's money back", refund_id: refund.id, fulfillment_id: id,
        amount_cents: refund.amount_cents, reason: refund.reason)

      refund
    end
  end

  # A declined fulfillment's pieces go back on the storefront, which puts a
  # listing that sold out back up for sale.
  def restore_stock
    items.each { |item| item.listing.restore_stock!(item.quantity) }
  end

  def shipment_is_trackable
    errors.add(:base, MISSING_DETAILS) if carrier.blank? || tracking_number.blank?
  end

  def must_move_to(target)
    return if can_transition_to?(target)

    errors.add(:base, "A fulfillment cannot move from #{status} to #{target}.")
  end

  def must_be_charged
    errors.add(:base, UNCHARGED) unless order.charged?
  end
end
