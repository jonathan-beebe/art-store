require_relative "../money"

module Domain
  module Notifications
    NotificationMessage = Data.define(:subject, :body, :url) do
      def self.item_sold(order_id, net)
        new(
          subject: "Item sold",
          body: "Order ##{order_id} is paid. #{net.format} is held until the customer confirms delivery.",
          url: nil
        )
      end

      def self.order_shipped(order_id, carrier, tracking_number)
        new(
          subject: "Order shipped",
          body: "Order ##{order_id} shipped with #{carrier}. Tracking number #{tracking_number}.",
          url: nil
        )
      end
    end
  end
end
