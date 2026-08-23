class Seller::ShipmentsController < Seller::BaseController
  def create
    @fulfillment = current_seller.fulfillments.find(params[:order_id])
    @fulfillment.ship!(carrier: params[:carrier], tracking_number: params[:tracking_number])

    redirect_to seller_order_path(@fulfillment), notice: "Marked shipped."
  rescue ActiveRecord::RecordInvalid => refusal
    @refusal = refusal.record.errors.full_messages.first

    render :refused, status: :unprocessable_content
  end
end
