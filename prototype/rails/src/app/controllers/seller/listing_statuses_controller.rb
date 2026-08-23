class Seller::ListingStatusesController < Seller::BaseController
  def create
    @listing = current_seller.listings.find(params[:listing_id])

    @listing.transition_to!(params[:status])

    redirect_to seller_listings_path,
      notice: %("#{@listing.title}" is now #{@listing.status.humanize.downcase}.)
  rescue TransitionError => refusal
    @refusal = refusal.message
    render :refused, status: :unprocessable_content
  end
end
