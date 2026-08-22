# Runs without Rails: ruby -Iapp app/domain/listings/stock_change_test.rb
require "minitest/autorun"
require_relative "stock_change"

module Domain
  module Listings
    class StockChangeTest < Minitest::Test
      def test_all_names_every_change_an_order_makes
        assert_equal %w[take restore keep], StockChange::ALL
      end
    end
  end
end
