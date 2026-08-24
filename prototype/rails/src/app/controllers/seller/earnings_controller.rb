class Seller::EarningsController < Seller::BaseController
  def show
    @fulfillments = current_seller.fulfillments.includes(order: :items).order(created_at: :desc, id: :desc)
    @balance = current_seller.escrow_balance
    @payouts = current_seller.payouts.order(period_start: :desc)
  end
end
