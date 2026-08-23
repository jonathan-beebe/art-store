class MagicLinkMailer < ApplicationMailer
  # The URL is passed in rather than built here: the plaintext token is
  # readable once, at issue time, and never again from the row.
  def sign_in
    @link = params[:link]
    @url = params[:url]
    @expiry_minutes = Rails.configuration.x.magic_links.expiry_minutes

    mail to: @link.email, subject: "Your sign-in link"
  end
end
