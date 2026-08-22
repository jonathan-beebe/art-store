module Orders
  # Verifying an email is what lets a guest's order reach the card form.
  class MarkAwaitingPayment
    def call(order:)
      order.update!(status: Domain::Orders::OrderStatus.after_verification(order.status))

      order
    end
  end
end
