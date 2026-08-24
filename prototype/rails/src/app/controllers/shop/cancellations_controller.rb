module Shop
  class CancellationsController < BaseController
    # An order past the point of cancelling is not an order this route can act
    # on, and neither is somebody else's — both answer the same 404.
    def create
      order = order_of_customer(params[:id])

      raise ActiveRecord::RecordNotFound unless order.cancellable?

      order.cancel!(by: current_customer)

      redirect_to shop_order_path(order), notice: "Order cancelled."
    end
  end
end
