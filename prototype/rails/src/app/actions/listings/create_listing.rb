module Listings
  class CreateListing
    def call(seller:, draft:, image: nil)
      listing = seller.listings.create!(
        draft.attributes.merge(
          slug: Domain::Listings::ListingSlug.first_free(draft.title, slugs_like(draft.title)),
          status: Domain::Listings::ListingStatus::DRAFT
        )
      )
      listing.image.attach(image) if image

      listing
    end

    private

    def slugs_like(title)
      Listing.where("slug LIKE ?", "#{Domain::Listings::ListingSlug.base(title)}%").pluck(:slug)
    end
  end
end
