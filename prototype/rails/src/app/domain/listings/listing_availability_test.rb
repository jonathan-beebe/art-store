require "minitest/autorun"
require_relative "listing_availability"

class ListingAvailabilityTest < Minitest::Test
  Availability = Domain::Listings::ListingAvailability
  Status = Domain::Listings::ListingStatus

  def test_a_for_sale_listing_is_on_the_storefront
    assert Availability.on_storefront?(Status::FOR_SALE)
  end

  def test_a_sold_listing_keeps_its_page
    assert Availability.on_storefront?(Status::SOLD)
  end

  def test_a_draft_listing_was_never_public
    refute Availability.on_storefront?(Status::DRAFT)
  end

  def test_an_archived_listing_leaves_the_storefront
    refute Availability.on_storefront?(Status::ARCHIVED)
  end

  def test_a_for_sale_listing_in_stock_is_purchasable
    assert Availability.purchasable?(Status::FOR_SALE, 1)
  end

  def test_a_for_sale_listing_with_no_stock_is_not_purchasable
    refute Availability.purchasable?(Status::FOR_SALE, 0)
  end

  def test_a_sold_listing_is_not_purchasable
    refute Availability.purchasable?(Status::SOLD, 3)
  end
end
