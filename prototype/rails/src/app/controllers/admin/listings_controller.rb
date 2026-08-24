class Admin::ListingsController < Admin::BaseController
  def index
    @status = filter_from(:status, Listing.statuses.keys)
    @seller_id = id_filter(:seller, :sel)
    @removed = filter_from(:removed, Listing::REMOVAL_STANDINGS, default: "any")
    @listings = Listing.with_status(@status).for_seller(@seller_id).removal_standing(@removed)
      .includes(:seller).order(created_at: :desc, id: :desc)
    @sellers = Seller.order(:created_at, :id)
  end

  def show
    @listing = Listing.includes(:seller).find(params[:id])
    @removals = @listing.removals
  end
end
