require "test_helper"

module Domain
  module Cart
    class CartQuantityTest < ActiveSupport::TestCase
      test "it takes what was asked for when the stock covers it" do
        assert_equal 2, CartQuantity.within_stock(requested: 2, available: 3)
      end

      test "it stops at what is left" do
        assert_equal 3, CartQuantity.within_stock(requested: 5, available: 3)
      end

      test "it holds at least one of a listing" do
        assert_raises(ArgumentError) { CartQuantity.within_stock(requested: 0, available: 3) }
      end

      test "it refuses a sold out listing" do
        assert_raises(ArgumentError) { CartQuantity.within_stock(requested: 1, available: 0) }
      end
    end
  end
end
