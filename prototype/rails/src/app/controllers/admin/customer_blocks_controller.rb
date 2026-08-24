class Admin::CustomerBlocksController < Admin::BaseController
  def create
    @customer = Customer.find(params[:customer_id])
    @customer.block!(reason: params[:reason], by: current_admin)

    redirect_to admin_customer_path(@customer), notice: "Customer blocked."
  rescue TransitionError => refusal
    redirect_to admin_customer_path(@customer), alert: refusal.message
  end

  def lift
    @customer = Customer.find(params[:customer_id])
    @customer.lift_block!

    redirect_to admin_customer_path(@customer), notice: "Block lifted."
  rescue TransitionError => refusal
    redirect_to admin_customer_path(@customer), alert: refusal.message
  end
end
