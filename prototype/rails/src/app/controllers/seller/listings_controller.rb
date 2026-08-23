class Seller::ListingsController < Seller::BaseController
  WINDOW_DAYS = 14

  before_action :set_listing, only: %i[show edit update]

  def index
    @listings = current_seller.listings.order(id: :desc).to_a
    @activity = activity_by_listing(@listings)
  end

  def show
    @totals = Domain::Reports::ActivityTotals.from(@listing.events.group(:event_type).count)
    @days = Domain::Reports::ActivityTimeline.last_days(
      event_counts_by_date(@listing), ends_on: Time.current, days: WINDOW_DAYS
    )
    @sales = @listing.order_items.includes(:order).order(id: :desc)
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

    redirect_to seller_listings_path, notice: %("#{@listing.title}" is updated.)
  end

  private

  def set_listing
    @listing = current_seller.listings.find(params[:id])
  end

  def listing_params
    params.expect(listing: %i[title description medium dimensions price quantity image])
  end

  def event_counts_by_date(listing)
    listing.events
           .pluck(:occurred_at, :event_type)
           .group_by { |occurred_at, _| occurred_at.to_date }
           .transform_values { |events| events.map(&:last).tally }
  end

  def activity_by_listing(listings)
    counts = ListingEvent.where(listing_id: listings.map(&:id)).group(:listing_id, :event_type).count

    listings.index_with do |listing|
      Domain::Reports::ActivityTotals.from(counts.select { |(id, _)| id == listing.id }.transform_keys(&:last))
    end
  end
end
