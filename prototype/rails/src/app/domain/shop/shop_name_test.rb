require "minitest/autorun"
require_relative "shop_name"

class ShopNameTest < Minitest::Test
  ShopName = Domain::Shop::ShopName

  def test_a_named_shop_reads_by_its_name
    assert_equal "Blue Kiln Studio", ShopName.of(shop_name: "Blue Kiln Studio", email: "ada@example.test")
  end

  def test_an_unnamed_shop_reads_by_the_address
    assert_equal "ada", ShopName.of(shop_name: nil, email: "ada@example.test")
  end

  def test_a_blank_name_reads_by_the_address
    assert_equal "ada", ShopName.of(shop_name: "   ", email: "ada@example.test")
  end
end
