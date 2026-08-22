module Orders
  class RollUpOrderStatus
    def call(order:)
      # Reloaded because the caller reached the order through a fulfillment it
      # has already changed, and the cached collection still holds the old row.
      order.update!(status: Domain::Orders::OrderStatus.from_fulfillments(order.fulfillments.reload.pluck(:status)))

      order
    end
  end
end
