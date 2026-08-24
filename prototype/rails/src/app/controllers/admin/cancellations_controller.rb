class Admin::CancellationsController < Admin::BaseController
  def create
    @order = Order.find(params[:order_id])
    @order.cancel!(by: current_admin, reason: params[:reason])

    redirect_to admin_order_path(@order), notice: "Order cancelled."
  rescue TransitionError => refusal
    redirect_to admin_order_path(@order), alert: refusal.message
  rescue ActiveRecord::RecordInvalid => refusal
    redirect_to admin_order_path(@order), alert: refusal.record.errors.full_messages.first
  end
end
