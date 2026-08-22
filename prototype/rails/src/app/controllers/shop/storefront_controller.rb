module Shop
  class StorefrontController < BaseController
    LISTINGS_PER_PAGE = 12
    TEXT_MATCH = "title LIKE :pattern OR description LIKE :pattern OR medium LIKE :pattern".freeze

    def show
      @search = Domain::Shop::ListingSearch.from_input(term: params[:q], medium: params[:medium])
      matches = matching(@search)
      @page = Domain::Shop::Page.of(
        requested: params[:page], size: LISTINGS_PER_PAGE, total_count: matches.count
      )
      @listings = matches.includes(:seller).order(id: :desc).offset(@page.offset).limit(@page.limit)
      @media = media_for_sale
    end

    private

    def matching(search)
      listings = Listing.for_sale
      listings = listings.where(TEXT_MATCH, pattern: search.like_pattern) if search.term?
      listings = listings.where(medium: search.medium) if search.medium?

      listings
    end

    def media_for_sale
      Listing.for_sale.where.not(medium: [nil, ""]).distinct.order(:medium).pluck(:medium)
    end
  end
end
