require "test_helper"

class StatusLabelTest < ActiveSupport::TestCase
  StatusLabel = Domain::Shop::StatusLabel

  test "it reads a stored status as a sentence" do
    assert_equal "Awaiting shipment", StatusLabel.humanize("awaiting_shipment")
  end

  test "a single word status keeps its shape" do
    assert_equal "Paid", StatusLabel.humanize("paid")
  end
end
