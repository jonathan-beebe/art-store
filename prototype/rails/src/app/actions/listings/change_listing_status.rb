module Listings
  class ChangeListingStatus
    def call(listing:, status:)
      listing.update!(status: Domain::Listings::ListingStatus.transition(listing.status, status))

      listing
    end
  end
end
