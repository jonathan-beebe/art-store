# Preview at /rails/mailers/magic_link_mailer/sign_in
class MagicLinkMailerPreview < ActionMailer::Preview
  def sign_in
    link = MagicLink.new(email: "artist@example.com", actor_type: :seller)
    url = Rails.application.routes.url_helpers.verify_magic_link_url(
      "preview-token", **Rails.application.config.action_mailer.default_url_options
    )

    MagicLinkMailer.with(link: link, url: url).sign_in
  end
end
