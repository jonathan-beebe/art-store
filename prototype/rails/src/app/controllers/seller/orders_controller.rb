# A seller's order is one fulfillment: the slice of a customer's order that
# belongs to them.
class Seller::OrdersController < Seller::BaseController
  def index
    by_status = current_seller.fulfillments.includes(order: :items).order(id: :desc).group_by(&:status)

    @groups = Fulfillment.statuses.keys.map { |status| [ status, by_status.fetch(status, []) ] }
  end

  def show
    @fulfillment = current_seller.fulfillments.includes(order: :items).find(params[:id])
  end
end
