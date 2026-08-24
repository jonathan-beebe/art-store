class Admin::RefundsController < Admin::BaseController
  def create
    @fulfillment = Fulfillment.find(params[:fulfillment_id])
    @fulfillment.refund!(reason: params[:reason], by: current_admin)

    redirect_to admin_fulfillment_path(@fulfillment), notice: "Refunded."
  rescue ActiveRecord::RecordInvalid => refusal
    redirect_to admin_fulfillment_path(@fulfillment), alert: refusal.record.errors.full_messages.first
  end
end
