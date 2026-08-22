# Runs without Rails: ruby -Iapp app/domain/orders/shipping_address_test.rb
require "minitest/autorun"
require_relative "shipping_address"

module Domain
  module Orders
    class ShippingAddressTest < Minitest::Test
      def test_it_carries_every_line_an_order_copies
        address = ShippingAddress.new(
          name: "Ada Lovelace", line1: "12 Analytical Way", line2: nil,
          city: "London", region: "Greater London", postal_code: "EC1A 1BB", country: "GB"
        )

        assert_equal(
          %i[name line1 line2 city region postal_code country],
          address.to_h.keys
        )
        assert_nil address.line2
      end
    end
  end
end
