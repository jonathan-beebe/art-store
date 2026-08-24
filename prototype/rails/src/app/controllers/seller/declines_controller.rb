class Seller::DeclinesController < Seller::BaseController
  def create
    @fulfillment = current_seller.fulfillments.find(params[:order_id])
    @fulfillment.decline!(reason: params[:reason], by: current_seller)

    redirect_to seller_order_path(@fulfillment), notice: "Declined. The money is on its way back."
  rescue ActiveRecord::RecordInvalid => refusal
    @refusal = refusal.record.errors.full_messages.first

    render :refused, status: :unprocessable_content
  end
end
