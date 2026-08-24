class Seller::ListingsController < Seller::BaseController
  WINDOW_DAYS = 14

  before_action :set_listing, only: %i[show edit update]
  rate_limit_guard :listing_write, by: -> { current_seller.id }, only: %i[create update]

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

    Story.tell("listing.create", "writing a new listing", seller_id: current_seller.id) do |story|
      next refuse(story, "the listing is incomplete", :new) unless @listing.save

      story.did("wrote the listing", seller_id: current_seller.id, listing_id: @listing.id, status: @listing.status)

      redirect_to seller_listings_path, notice: %("#{@listing.title}" is saved as a draft.)
    end
  end

  def edit
  end

  def update
    Story.tell("listing.update", "editing a listing",
      seller_id: current_seller.id, listing_id: @listing.id) do |story|
      next refuse(story, "the edit is incomplete", :edit) unless @listing.update(listing_params)

      story.did("edited the listing", seller_id: current_seller.id, listing_id: @listing.id)

      redirect_to seller_listings_path, notice: %("#{@listing.title}" is updated.), status: :see_other
    end
  end

  private

  # A form the model would not take comes back holding the seller's own text.
  def refuse(story, message, template)
    story.refused(message, seller_id: current_seller.id, fields: @listing.errors.attribute_names.map(&:to_s))

    render template, status: :unprocessable_content
  end

  def set_listing
    @listing = current_seller.listings.find(params[:id])
  end

  def listing_params
    params.expect(listing: %i[title description medium dimensions price quantity image])
  end

  # A tripped `listing_write` comes back on the same form the listing was
  # being written on, holding the seller's own text: `set_listing` already
  # loaded `@listing` for an edit, and a create builds the same unsaved
  # record `create` itself would have.
  def render_too_many_requests(trip)
    @listing ||= current_seller.listings.new(listing_params)
    flash.now[:alert] = rate_limit_message(trip)

    render action_name == "create" ? :new : :edit, status: :too_many_requests
  end
end
