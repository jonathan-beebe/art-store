require "test_helper"

module Domain
  module Listings
    class StockChangeTest < ActiveSupport::TestCase
      def test_all_names_every_change_an_order_makes
        assert_equal %w[take restore keep], StockChange::ALL
      end
    end
  end
end
