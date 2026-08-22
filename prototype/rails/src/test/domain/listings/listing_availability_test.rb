require "test_helper"

class ListingAvailabilityTest < ActiveSupport::TestCase
  Availability = Domain::Listings::ListingAvailability
  Status = Domain::Listings::ListingStatus

  test "a for sale listing is on the storefront" do
    assert Availability.on_storefront?(Status::FOR_SALE)
  end

  test "a sold listing keeps its page" do
    assert Availability.on_storefront?(Status::SOLD)
  end

  test "a draft listing was never public" do
    refute Availability.on_storefront?(Status::DRAFT)
  end

  test "an archived listing leaves the storefront" do
    refute Availability.on_storefront?(Status::ARCHIVED)
  end

  test "a for sale listing in stock is purchasable" do
    assert Availability.purchasable?(Status::FOR_SALE, 1)
  end

  test "a for sale listing with no stock is not purchasable" do
    refute Availability.purchasable?(Status::FOR_SALE, 0)
  end

  test "a sold listing is not purchasable" do
    refute Availability.purchasable?(Status::SOLD, 3)
  end
end
