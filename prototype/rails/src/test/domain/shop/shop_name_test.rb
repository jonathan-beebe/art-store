require "test_helper"

class ShopNameTest < ActiveSupport::TestCase
  ShopName = Domain::Shop::ShopName

  test "a named shop reads by its name" do
    assert_equal "Blue Kiln Studio", ShopName.of(shop_name: "Blue Kiln Studio", email: "ada@example.test")
  end

  test "an unnamed shop reads by the address" do
    assert_equal "ada", ShopName.of(shop_name: nil, email: "ada@example.test")
  end

  test "a blank name reads by the address" do
    assert_equal "ada", ShopName.of(shop_name: "   ", email: "ada@example.test")
  end
end
