class Admin::SellersController < Admin::BaseController
  def index
    @sellers = Seller.directory
  end

  def show
    @seller = Seller.find(params[:id])
    @listings = @seller.listings.includes(:seller).order(created_at: :desc, id: :desc)
    @fulfillments = @seller.fulfillments.includes(:seller, :order).order(created_at: :desc, id: :desc)
    @payouts = @seller.payouts.order(period_start: :desc, id: :desc)
    @balance = @seller.escrow_balance
  end
end
