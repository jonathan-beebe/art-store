module MagicLinkSender
  extend ActiveSupport::Concern

  private

  # Returns the link. The token only reaches the visitor through the delivery,
  # and an address that is not an address comes back unsaved and undelivered.
  def send_magic_link(email:, actor_type:, redirect_to: nil)
    link, token = MagicLink.issue(email: email, actor_type: actor_type, redirect_to: redirect_to)
    return link unless link.persisted?

    MagicLinkDelivery.build(flash).deliver(email: link.email, url: verify_magic_link_url(token))

    link
  end
end
