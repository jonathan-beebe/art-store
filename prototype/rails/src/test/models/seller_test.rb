require "test_helper"

class SellerTest < ActiveSupport::TestCase
  test "the address is normalized on the way in" do
    seller = create_seller(email: "  Artist@Example.COM ")

    assert_equal "artist@example.com", seller.email
  end

  test "two sellers cannot hold the same address" do
    create_seller(email: "artist@example.com")

    assert_raises(ActiveRecord::RecordNotUnique) { create_seller(email: "ARTIST@example.com") }
  end

  test "a first link for an address creates the seller" do
    seller = Seller.claim("newcomer@example.com")

    assert_equal "newcomer@example.com", seller.email
    assert_equal 1, Seller.count
  end

  test "a first link marks the address verified" do
    freeze_time do
      assert_equal Time.current, Seller.claim("newcomer@example.com").email_verified_at
    end
  end

  test "a later link returns the seller already holding the address" do
    existing = create_seller

    assert_equal existing, Seller.claim(existing.email)
    assert_equal 1, Seller.count
  end

  test "a later link leaves the original verification time alone" do
    existing = create_seller(email_verified_at: 3.days.ago)

    assert_equal existing.email_verified_at.to_i, Seller.claim(existing.email).email_verified_at.to_i
  end

  test "an address differing only in case reaches the same seller" do
    existing = create_seller(email: "artist@example.com")

    assert_equal existing, Seller.claim("ARTIST@Example.com")
  end
end
