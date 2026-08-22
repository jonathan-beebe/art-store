require "test_helper"

class FlashMagicLinkDeliveryTest < ActiveSupport::TestCase
  test "it hands the link to the debug alert the layouts render" do
    flash = ActionDispatch::Flash::FlashHash.new

    FlashMagicLinkDelivery.new(flash).deliver(email: "artist@example.com", url: "http://localhost:3000/auth/magic/abc")

    assert_equal "http://localhost:3000/auth/magic/abc", flash[:debug_magic_link]
  end
end
