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
end
