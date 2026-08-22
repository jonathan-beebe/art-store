require "minitest/autorun"
require_relative "status_label"

class StatusLabelTest < Minitest::Test
  StatusLabel = Domain::Shop::StatusLabel

  def test_it_reads_a_stored_status_as_a_sentence
    assert_equal "Awaiting shipment", StatusLabel.humanize("awaiting_shipment")
  end

  def test_a_single_word_status_keeps_its_shape
    assert_equal "Paid", StatusLabel.humanize("paid")
  end
end
