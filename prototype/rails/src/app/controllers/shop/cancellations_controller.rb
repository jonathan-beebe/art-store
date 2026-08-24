module Shop
  class CancellationsController < BaseController
    # Somebody else's order is not this customer's to act on, and "not found"
    # tells them nothing about whether it exists. An order this customer owns
    # but can no longer call off is a domain refusal instead: cancel! raises
    # and the customer reads why.
    def create
      order = order_of_customer(params[:id])
      order.cancel!(by: current_customer)

      redirect_to shop_order_path(order), notice: "Order cancelled."
    rescue TransitionError => refusal
      redirect_to shop_order_path(order), alert: refusal.message
    end
  end
end
