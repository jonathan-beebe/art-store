class Seller::ListingStatusesController < Seller::BaseController
  def create
    @listing = current_seller.listings.find(params[:listing_id])

    Listings::ChangeListingStatus.new.call(listing: @listing, status: params[:status])

    redirect_to seller_listings_path,
      notice: %("#{@listing.title}" is now #{Domain::Reports::StatusLabel.of(@listing.status).downcase}.)
  rescue Domain::TransitionError => refusal
    @refusal = refusal.message
    render :refused, status: :unprocessable_content
  end
end
