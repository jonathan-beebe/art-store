require "test_helper"

module Domain
  module Reports
    class StatusLabelTest < ActiveSupport::TestCase
      test "it reads a single word status as a sentence" do
        assert_equal "Draft", StatusLabel.of("draft")
      end

      test "it replaces the underscores of a multi word status" do
        assert_equal "For sale", StatusLabel.of("for_sale")
        assert_equal "Awaiting shipment", StatusLabel.of("awaiting_shipment")
        assert_equal "Pending verification", StatusLabel.of("pending_verification")
      end

      test "it labels a symbol status" do
        assert_equal "Paid out", StatusLabel.of(:paid_out)
      end
    end
  end
end
