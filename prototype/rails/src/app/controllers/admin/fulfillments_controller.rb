# A fulfillment is one seller's slice of an order, which is the unit the
# platform ships, holds money against, and refunds.
class Admin::FulfillmentsController < Admin::BaseController
  def index
    @status = filter_from(:status, Fulfillment.statuses.keys)
    @seller_id = id_filter(:seller, :sel)
    @fulfillments = Fulfillment.with_status(@status).for_seller(@seller_id)
      .includes(:seller, :order).order(created_at: :desc, id: :desc)
    @sellers = Seller.order(:created_at, :id)
  end

  def show
    @fulfillment = Fulfillment.includes(:seller, order: { items: :listing }).find(params[:id])
    @items = @fulfillment.items
    @refunds = @fulfillment.refunds.order(created_at: :desc, id: :desc)
  end
end
