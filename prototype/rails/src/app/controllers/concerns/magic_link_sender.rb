module MagicLinkSender
  extend ActiveSupport::Concern

  private

  # Returns the link. The token only reaches the visitor through the mail (and
  # the debug alert where that is on), and an address that is not an address
  # comes back unsaved and unsent.
  def send_magic_link(email:, actor_type:, redirect_to: nil)
    link, token = MagicLink.issue(email: email, actor_type: actor_type, redirect_to: redirect_to)
    return link unless link.persisted?

    url = verify_magic_link_url(token)
    MagicLinkMailer.with(link: link, url: url).sign_in.deliver_later
    flash[:debug_magic_link] = url if Rails.configuration.x.magic_links.debug_alert

    link
  end
end
