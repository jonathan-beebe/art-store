require "test_helper"

module Domain
  module Reports
    class StatusLabelTest < ActiveSupport::TestCase
      def test_it_reads_a_single_word_status_as_a_sentence
        assert_equal "Draft", StatusLabel.of("draft")
      end

      def test_it_replaces_the_underscores_of_a_multi_word_status
        assert_equal "For sale", StatusLabel.of("for_sale")
        assert_equal "Awaiting shipment", StatusLabel.of("awaiting_shipment")
        assert_equal "Pending verification", StatusLabel.of("pending_verification")
      end

      def test_it_labels_a_symbol_status
        assert_equal "Paid out", StatusLabel.of(:paid_out)
      end
    end
  end
end
