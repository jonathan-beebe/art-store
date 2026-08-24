module MagicLinkSender
  extend ActiveSupport::Concern

  private

  # Returns the link, or nil once a trip has already rendered the 429 in its
  # place. The token only reaches the visitor through the mail (and the debug
  # alert where that is on), and an address that is not an address comes back
  # unsaved and unsent.
  #
  # `magic_link_request` keys on the address and, separately, the ip, so
  # either alone can trip it; both checks run before the story starts, so a
  # trip writes no `will` line and issues no link.
  def send_magic_link(email:, actor_type:, redirect_to: nil)
    return nil if rate_limit_trip!(:magic_link_request, by: email.to_s.strip.downcase)
    return nil if rate_limit_trip!(:magic_link_request, by: rate_limit_client_ip)

    Story.tell("magic_link.request", "sending a sign-in link", actor_type: actor_type.to_s) do |story|
      link, token = MagicLink.issue(email: email, actor_type: actor_type, redirect_to: redirect_to)

      unless link.persisted?
        story.refused("that is not an address a sign-in link can reach", actor_type: actor_type.to_s)

        next link
      end

      url = verify_magic_link_url(token)
      MagicLinkMailer.with(link: link, url: url).sign_in.deliver_later
      flash[:debug_magic_link] = url if Rails.configuration.x.magic_links.debug_alert

      story.did("sent a sign-in link", actor_type: actor_type.to_s, magic_link_id: link.id)

      link
    end
  end
end
