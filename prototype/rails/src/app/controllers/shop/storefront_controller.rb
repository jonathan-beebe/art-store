module Shop
  class StorefrontController < BaseController
    LISTINGS_PER_PAGE = 12

    def show
      @term = params[:q].to_s.strip.presence
      @medium = params[:medium].to_s.strip.presence
      matches = Listing.search(term: @term, medium: @medium)
      @page = Page.of(requested: params[:page], size: LISTINGS_PER_PAGE, total_count: matches.count)
      @listings = matches.includes(:seller).order(id: :desc).offset(@page.offset).limit(@page.limit)
      @media = Listing.media_for_sale
    end
  end
end
