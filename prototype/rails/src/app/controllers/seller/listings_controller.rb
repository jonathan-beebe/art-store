class Seller::ListingsController < Seller::BaseController
  WINDOW_DAYS = 14

  before_action :set_listing, only: %i[show edit update]

  def index
    @listings = current_seller.listings.order(created_at: :desc, id: :desc).to_a
    @activity = ListingEvent.totals_by_listing(@listings)
  end

  def show
    @totals = @listing.activity_totals
    @days = @listing.activity_by_day(days: WINDOW_DAYS)
    @sales = @listing.order_items.includes(:order).order(created_at: :desc, id: :desc)
  end

  def new
    @listing = current_seller.listings.new(quantity: 1)
  end

  def create
    @listing = current_seller.listings.new(listing_params)

    return render :new, status: :unprocessable_content unless @listing.save

    redirect_to seller_listings_path, notice: %("#{@listing.title}" is saved as a draft.)
  end

  def edit
  end

  def update
    return render :edit, status: :unprocessable_content unless @listing.update(listing_params)

    redirect_to seller_listings_path, notice: %("#{@listing.title}" is updated.), status: :see_other
  end

  private

  def set_listing
    @listing = current_seller.listings.find(params[:id])
  end

  def listing_params
    params.expect(listing: %i[title description medium dimensions price quantity image])
  end
end
