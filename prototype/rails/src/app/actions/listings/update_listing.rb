module Listings
  class UpdateListing
    # The slug stays as it was: a retitled listing keeps the storefront URL it
    # was shared under.
    def call(listing:, draft:, image: nil)
      listing.update!(draft.attributes)
      listing.image.attach(image) if image

      listing
    end
  end
end
