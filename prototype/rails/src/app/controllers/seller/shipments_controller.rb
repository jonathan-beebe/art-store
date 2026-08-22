class Seller::ShipmentsController < Seller::BaseController
  MISSING_DETAILS = "A shipment needs a carrier and a tracking number.".freeze

  def create
    @fulfillment = current_seller.fulfillments.find(params[:order_id])
    carrier = params[:carrier].to_s.strip
    tracking_number = params[:tracking_number].to_s.strip

    return refuse(MISSING_DETAILS) if carrier.empty? || tracking_number.empty?

    Fulfillments::MarkShipped.new.call(
      fulfillment: @fulfillment, carrier: carrier, tracking_number: tracking_number, now: Time.current
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
