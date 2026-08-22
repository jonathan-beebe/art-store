require "test_helper"

module Domain
  module Orders
    class ShipmentDetailsTest < ActiveSupport::TestCase
      test "a carrier and a tracking number are complete" do
        assert_predicate submitted(carrier: "Royal Mail", tracking_number: "RM123456789GB"), :complete?
      end

      test "surrounding space is not part of either field" do
        details = submitted(carrier: "  Royal Mail  ", tracking_number: "  RM123456789GB  ")

        assert_equal "Royal Mail", details.carrier
        assert_equal "RM123456789GB", details.tracking_number
      end

      test "a shipment with no carrier is incomplete" do
        refute_predicate submitted(carrier: " ", tracking_number: "RM123456789GB"), :complete?
      end

      test "a shipment with no tracking number is incomplete" do
        refute_predicate submitted(carrier: "Royal Mail", tracking_number: ""), :complete?
      end

      test "a missing field is incomplete rather than an error" do
        refute_predicate submitted(carrier: nil, tracking_number: nil), :complete?
      end

      private

      def submitted(carrier:, tracking_number:)
        ShipmentDetails.from_input(carrier: carrier, tracking_number: tracking_number)
      end
    end
  end
end
