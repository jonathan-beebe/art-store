class Seller::ListingsController < Seller::BaseController
  def index
    @listings = current_seller.listings.order(id: :desc).to_a
    @activity = activity_by_listing(@listings)
  end

  def new
    @fields = { title: "", description: "", medium: "", dimensions: "", price: "", quantity: 1 }
    @errors = {}
  end

  def create
    @fields = submitted_fields
    @errors = Domain::Listings::ListingDraft.errors_for(@fields)
    return render :new, status: :unprocessable_content if @errors.any?

    listing = Listings::CreateListing.new.call(
      seller: current_seller, draft: Domain::Listings::ListingDraft.from(@fields), image: submitted_image
    )

    redirect_to seller_listings_path, notice: %("#{listing.title}" is saved as a draft.)
  end

  def edit
    @listing = owned_listing
    @fields = fields_of(@listing)
    @errors = {}
  end

  def update
    @listing = owned_listing
    @fields = submitted_fields
    @errors = Domain::Listings::ListingDraft.errors_for(@fields)
    return render :edit, status: :unprocessable_content if @errors.any?

    Listings::UpdateListing.new.call(
      listing: @listing, draft: Domain::Listings::ListingDraft.from(@fields), image: submitted_image
    )

    redirect_to seller_listings_path, notice: %("#{@listing.title}" is updated.)
  end

  private

  def owned_listing
    current_seller.listings.find(params[:id])
  end

  def submitted_fields
    params.expect(listing: %i[title description medium dimensions price quantity])
          .to_h
          .symbolize_keys
          .merge(image_content_type: submitted_image&.content_type)
  end

  def submitted_image
    params.dig(:listing, :image).presence
  end

  def fields_of(listing)
    {
      title: listing.title,
      description: listing.description,
      medium: listing.medium,
      dimensions: listing.dimensions,
      price: format("%d.%02d", listing.price_cents / 100, listing.price_cents % 100),
      quantity: listing.quantity
    }
  end

  def activity_by_listing(listings)
    counts = ListingEvent.where(listing_id: listings.map(&:id)).group(:listing_id, :event_type).count

    listings.index_with do |listing|
      Domain::Reports::ActivityTotals.from(counts.select { |(id, _)| id == listing.id }.transform_keys(&:last))
    end
  end
end
