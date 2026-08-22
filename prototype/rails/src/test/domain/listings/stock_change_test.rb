require "test_helper"

module Domain
  module Listings
    class StockChangeTest < ActiveSupport::TestCase
      test "all names every change an order makes" do
        assert_equal %w[take restore keep], StockChange::ALL
      end
    end
  end
end
