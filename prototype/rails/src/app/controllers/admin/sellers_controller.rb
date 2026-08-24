class Admin::SellersController < Admin::BaseController
  def show
    @seller = Seller.find(params[:id])
    @listing_count = @seller.listings.count
  end
end
