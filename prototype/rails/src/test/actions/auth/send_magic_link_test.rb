require "test_helper"

module Auth
  class SendMagicLinkTest < ActiveSupport::TestCase
    class DeliveryRecorder
      attr_reader :email, :url

      def deliver(email:, url:)
        @email = email
        @url = url
      end
    end

    setup do
      @delivery = DeliveryRecorder.new
    end

    test "it issues a link for the address" do
      send_link(email: "artist@example.com")

      assert_equal "artist@example.com", MagicLink.sole.email
    end

    test "it normalizes the address before storing it" do
      send_link(email: "  Artist@Example.COM ")

      assert_equal "artist@example.com", MagicLink.sole.email
    end

    test "it stores only the digest of the token it hands out" do
      send_link

      token = @delivery.url.split("/").last

      assert_equal Domain::Auth::MagicLinkToken.digest(token), MagicLink.sole.token_digest
    end

    test "it delivers the verification url for the token" do
      send_link

      assert_match %r{\Ahttp://magic\.test/[0-9a-f]{64}\z}, @delivery.url
    end

    test "it delivers to the normalized address" do
      send_link(email: "Artist@Example.COM")

      assert_equal "artist@example.com", @delivery.email
    end

    test "it records which side of the marketplace asked" do
      send_link(actor_type: Domain::Auth::ActorType::CUSTOMER)

      assert_equal Domain::Auth::ActorType::CUSTOMER, MagicLink.sole.actor_type
    end

    test "it carries the destination the visitor was headed for" do
      send_link(redirect_to: "/orders/7/pay")

      assert_equal "/orders/7/pay", MagicLink.sole.redirect_to
    end

    test "it expires the link after the configured window" do
      freeze_time do
        send_link

        assert_equal Rails.configuration.x.magic_links.expiry_minutes.minutes.from_now, MagicLink.sole.expires_at
      end
    end

    test "it leaves the link unconsumed" do
      send_link

      assert_nil MagicLink.sole.consumed_at
    end

    test "two links for the same address carry different tokens" do
      send_link
      send_link

      assert_equal 2, MagicLink.distinct.count(:token_digest)
    end

    private

    def send_link(email: "artist@example.com", actor_type: Domain::Auth::ActorType::SELLER, redirect_to: nil)
      SendMagicLink
        .new(delivery: @delivery, link_url: ->(token) { "http://magic.test/#{token}" })
        .call(email: email, actor_type: actor_type, redirect_to: redirect_to)
    end
  end
end
