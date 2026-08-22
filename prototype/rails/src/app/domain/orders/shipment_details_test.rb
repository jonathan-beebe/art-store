# Runs without Rails: ruby -Iapp app/domain/orders/shipment_details_test.rb
require "minitest/autorun"
require_relative "shipment_details"

module Domain
  module Orders
    class ShipmentDetailsTest < Minitest::Test
      def test_a_carrier_and_a_tracking_number_are_complete
        assert_predicate submitted(carrier: "Royal Mail", tracking_number: "RM123456789GB"), :complete?
      end

      def test_surrounding_space_is_not_part_of_either_field
        details = submitted(carrier: "  Royal Mail  ", tracking_number: "  RM123456789GB  ")

        assert_equal "Royal Mail", details.carrier
        assert_equal "RM123456789GB", details.tracking_number
      end

      def test_a_shipment_with_no_carrier_is_incomplete
        refute_predicate submitted(carrier: " ", tracking_number: "RM123456789GB"), :complete?
      end

      def test_a_shipment_with_no_tracking_number_is_incomplete
        refute_predicate submitted(carrier: "Royal Mail", tracking_number: ""), :complete?
      end

      def test_a_missing_field_is_incomplete_rather_than_an_error
        refute_predicate submitted(carrier: nil, tracking_number: nil), :complete?
      end

      private

      def submitted(carrier:, tracking_number:)
        ShipmentDetails.from_input(carrier: carrier, tracking_number: tracking_number)
      end
    end
  end
end
