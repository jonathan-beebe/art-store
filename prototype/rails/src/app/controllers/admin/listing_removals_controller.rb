class Admin::ListingRemovalsController < Admin::BaseController
  def create
    @listing = Listing.find(params[:listing_id])
    @listing.remove!(kind: params[:kind], reason: params[:reason], by: current_admin)

    redirect_to admin_listing_path(@listing), notice: "Listing removed."
  rescue TransitionError => refusal
    redirect_to admin_listing_path(@listing), alert: refusal.message
  end

  def lift
    @listing = Listing.find(params[:listing_id])
    @listing.lift_removal!

    redirect_to admin_listing_path(@listing), notice: "Removal lifted."
  rescue TransitionError => refusal
    redirect_to admin_listing_path(@listing), alert: refusal.message
  end
end
