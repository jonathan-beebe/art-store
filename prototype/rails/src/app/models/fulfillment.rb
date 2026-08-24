class Fulfillment < ApplicationRecord
  prefixed_id :ful

  belongs_to :order
  belongs_to :seller
  has_many :ledger_entries, dependent: :destroy
  has_many :conversations, as: :subject, dependent: :destroy

  enum :status, {
    awaiting_shipment: "awaiting_shipment",
    shipped: "shipped",
    delivered: "delivered"
  }

  TRANSITIONS = {
    "awaiting_shipment" => %w[shipped].freeze,
    "shipped" => %w[delivered].freeze,
    "delivered" => [].freeze
  }.freeze

  # A fulfillment the customer is waiting on rather than the seller.
  DEPARTED = %w[shipped delivered].freeze

  # The platform takes a tenth of every sale off the top; the seller nets the
  # rest, and that net is what moves through escrow.
  PLATFORM_FEE_PERCENT = 10

  # A shipment the customer can follow needs both parts, and the seller's form
  # asks for the pair, so the refusal is one sentence.
  MISSING_DETAILS = "A shipment needs a carrier and a tracking number.".freeze

  scope :with_status, ->(status) { where(status: status) if status.present? }
  scope :for_seller, ->(seller_id) { where(seller_id: seller_id) if seller_id.present? }

  normalizes :carrier, :tracking_number, with: ->(field) { field.strip.presence }

  validate :shipment_is_trackable, on: :ship
  validate(on: :ship) { must_move_to("shipped") }
  validate(on: :deliver) { must_move_to("delivered") }

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

  # An order may span sellers. These are the lines of it this fulfillment
  # ships.
  def items
    order.items.select { |item| item.seller_id == seller_id }
  end

  # The money sent back to the customer against this fulfillment, newest
  # first. Nothing issues a refund, so the history is empty.
  def refunds
    []
  end

  def can_transition_to?(status)
    TRANSITIONS.fetch(self.status, []).include?(status.to_s)
  end

  def departed?
    DEPARTED.include?(status)
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

  def shipment_is_trackable
    errors.add(:base, MISSING_DETAILS) if carrier.blank? || tracking_number.blank?
  end

  def must_move_to(target)
    return if can_transition_to?(target)

    errors.add(:base, "A fulfillment cannot move from #{status} to #{target}.")
  end
end
