require "test_helper"

class MailMagicLinkDeliveryTest < ActiveSupport::TestCase
  test "it refuses to send until email is wired up" do
    error = assert_raises(NotImplementedError) do
      MailMagicLinkDelivery.new.deliver(email: "artist@example.com", url: "http://localhost:3000/auth/magic/abc")
    end

    assert_equal "Email delivery is not implemented yet", error.message
  end
end
