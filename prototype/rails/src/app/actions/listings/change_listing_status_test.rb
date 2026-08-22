require "commerce_test_case"

module Listings
  class ChangeListingStatusTest < CommerceTestCase
    def test_a_draft_goes_on_sale
      art = listing(seller, status: Domain::Listings::ListingStatus::DRAFT)

      ChangeListingStatus.new.call(listing: art, status: Domain::Listings::ListingStatus::FOR_SALE)

      assert_equal Domain::Listings::ListingStatus::FOR_SALE, art.reload.status
    end

    def test_a_listing_on_sale_is_archived
      art = listing(seller, status: Domain::Listings::ListingStatus::FOR_SALE)

      ChangeListingStatus.new.call(listing: art, status: Domain::Listings::ListingStatus::ARCHIVED)

      assert_equal Domain::Listings::ListingStatus::ARCHIVED, art.reload.status
    end

    def test_a_move_the_lifecycle_refuses_raises
      art = listing(seller, status: Domain::Listings::ListingStatus::DRAFT)

      assert_raises(Domain::TransitionError) do
        ChangeListingStatus.new.call(listing: art, status: Domain::Listings::ListingStatus::SOLD)
      end

      assert_equal Domain::Listings::ListingStatus::DRAFT, art.reload.status
    end
  end
end
