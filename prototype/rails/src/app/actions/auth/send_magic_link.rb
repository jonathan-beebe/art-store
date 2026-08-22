module Auth
  class SendMagicLink
    TOKEN_BYTES = 32

    # +link_url+ turns the token into the URL the visitor clicks; the host it
    # needs belongs to the request, so the caller supplies it.
    def initialize(delivery:, link_url:)
      @delivery = delivery
      @link_url = link_url
    end

    def call(email:, actor_type:, redirect_to: nil, now: Time.current)
      token = SecureRandom.hex(TOKEN_BYTES)

      link = MagicLink.create!(
        token_digest: Domain::Auth::MagicLinkToken.digest(token),
        email: email,
        actor_type: actor_type,
        redirect_to: redirect_to,
        expires_at: now + Rails.configuration.x.magic_links.expiry_minutes.minutes
      )

      @delivery.deliver(email: link.email, url: @link_url.call(token))

      link
    end
  end
end
