class Notification < ApplicationRecord
  prefixed_id :ntf

  belongs_to :recipient, polymorphic: true

  scope :unread, -> { where(read_at: nil) }

  def self.item_sold(fulfillment)
    deliver(
      recipient: fulfillment.seller,
      subject: "Item sold",
      body: "Order #{fulfillment.order_id} is paid. #{fulfillment.net.format} is held until the customer confirms delivery."
    )
  end

  def self.order_shipped(fulfillment)
    deliver(
      recipient: fulfillment.order.customer,
      subject: "Order shipped",
      body: "Order #{fulfillment.order_id} shipped with #{fulfillment.carrier}. " \
            "Tracking number #{fulfillment.tracking_number}."
    )
  end

  # The seller pulled out of the sale, so only the customer learns anything
  # they did not already know.
  def self.fulfillment_declined(fulfillment, refund)
    deliver(
      recipient: fulfillment.order.customer,
      subject: "Order refunded",
      body: "#{fulfillment.seller.display_name} could not fulfill part of order #{fulfillment.order_id}. " \
            "#{refund.amount.format} is on its way back to you. Reason: #{refund.reason}"
    )
  end

  # The platform decided over both their heads, so both sides are told.
  def self.fulfillment_refunded(fulfillment, refund)
    deliver(
      recipient: fulfillment.order.customer,
      subject: "Order refunded",
      body: "#{refund.amount.format} of order #{fulfillment.order_id} has been refunded. Reason: #{refund.reason}"
    )
    deliver(
      recipient: fulfillment.seller,
      subject: "Sale refunded",
      body: "The platform refunded #{refund.amount.format} of order #{fulfillment.order_id}. " \
            "#{fulfillment.net.format} has been taken back off your balance. Reason: #{refund.reason}"
    )
  end

  # An admin called an order off, which is news to the customer waiting on it
  # and to every seller who was going to ship part of it.
  def self.order_cancelled(order, reason:)
    deliver(
      recipient: order.customer,
      subject: "Order cancelled",
      body: "Order #{order.id} has been cancelled. Nothing was charged. Reason: #{reason}"
    )
    order.fulfillments.includes(:seller).each do |fulfillment|
      deliver(
        recipient: fulfillment.seller,
        subject: "Order cancelled",
        body: "Order #{order.id} has been cancelled. Your items are back on the storefront. Reason: #{reason}"
      )
    end
  end

  # The url is the recipient's own thread page: the three sites carry the same
  # conversation under three paths.
  def self.new_message(message, url:)
    conversation = message.conversation

    deliver(
      recipient: conversation.counterpart_of(message.sender),
      subject: "New message",
      body: "You have a new message about #{conversation.topic}.",
      url: url
    )
  end

  private_class_method def self.deliver(attributes)
    Story.tell("notification.write", "writing a notification to the inbox",
      recipient_type: attributes[:recipient].model_name.singular, subject: attributes[:subject]) do |story|
      notification = create!(attributes)

      story.did("wrote the notification to the inbox",
        notification_id: notification.id, recipient_type: notification.recipient_type.downcase,
        recipient_id: notification.recipient_id, subject: notification.subject)

      notification.deliver_by_email

      notification
    end
  end

  def read!(at: Time.current)
    update!(read_at: at)
  end

  # The prototype delivers to the in-app inbox only. Mail hangs off this hook,
  # the way sign-in links hang off MagicLinkMailer.
  def deliver_by_email
    Story.tell("notification.deliver", "handing the notification to a transport",
      notification_id: id, transport: "inbox") do |story|
      story.did("the inbox is the only transport", notification_id: id, transport: "inbox")
    end
  end
end
