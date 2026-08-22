class Seller::ShipmentsController < Seller::BaseController
  MISSING_DETAILS = "A shipment needs a carrier and a tracking number.".freeze

  def create
    @fulfillment = current_seller.fulfillments.find(params[:order_id])
    details = Domain::Orders::ShipmentDetails.from_input(
      carrier: params[:carrier], tracking_number: params[:tracking_number]
    )

    return refuse(MISSING_DETAILS) unless details.complete?

    Fulfillments::MarkShipped.new.call(
      fulfillment: @fulfillment, carrier: details.carrier, tracking_number: details.tracking_number, now: Time.current
    )

    redirect_to seller_order_path(@fulfillment), notice: "Marked shipped."
  rescue Domain::TransitionError => refusal
    refuse(refusal.message)
  end

  private

  def refuse(message)
    @refusal = message

    render :refused, status: :unprocessable_content
  end
end
