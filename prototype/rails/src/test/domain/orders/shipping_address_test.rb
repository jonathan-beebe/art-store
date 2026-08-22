require "test_helper"

module Domain
  module Orders
    class ShippingAddressTest < ActiveSupport::TestCase
      test "it carries every line an order copies" do
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
