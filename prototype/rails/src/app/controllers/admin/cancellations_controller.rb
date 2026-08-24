class Admin::CancellationsController < Admin::BaseController
  def create
    @order = Order.find(params[:order_id])
    @order.cancel!(by: current_admin)

    redirect_to admin_order_path(@order), notice: "Order cancelled."
  rescue TransitionError => refusal
    redirect_to admin_order_path(@order), alert: refusal.message
  end
end
