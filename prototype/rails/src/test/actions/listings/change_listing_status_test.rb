require "commerce_test_case"

module Listings
  class ChangeListingStatusTest < CommerceTestCase
    test "a draft goes on sale" do
      art = listing(seller, status: Domain::Listings::ListingStatus::DRAFT)

      ChangeListingStatus.new.call(listing: art, status: Domain::Listings::ListingStatus::FOR_SALE)

      assert_equal Domain::Listings::ListingStatus::FOR_SALE, art.reload.status
    end

    test "a listing on sale is archived" do
      art = listing(seller, status: Domain::Listings::ListingStatus::FOR_SALE)

      ChangeListingStatus.new.call(listing: art, status: Domain::Listings::ListingStatus::ARCHIVED)

      assert_equal Domain::Listings::ListingStatus::ARCHIVED, art.reload.status
    end

    test "a move the lifecycle refuses raises" do
      art = listing(seller, status: Domain::Listings::ListingStatus::DRAFT)

      assert_raises(Domain::TransitionError) do
        ChangeListingStatus.new.call(listing: art, status: Domain::Listings::ListingStatus::SOLD)
      end

      assert_equal Domain::Listings::ListingStatus::DRAFT, art.reload.status
    end
  end
end
