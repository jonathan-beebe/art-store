require "digest"

class MagicLink < ApplicationRecord
  include EmailAddress

  TOKEN_BYTES = 32

  enum :actor_type, { seller: "seller", customer: "customer", admin: "admin" }

  validates :email, format: { with: EmailAddress::SHAPE }

  # Returns the row beside the plaintext token, which is the last time anyone
  # can read it: only the digest is stored, so a leaked row cannot be replayed
  # as a link. An address that is not an address comes back unsaved.
  def self.issue(email:, actor_type:, redirect_to: nil, now: Time.current)
    token = SecureRandom.hex(TOKEN_BYTES)
    link = new(
      token_digest: digest(token),
      email: email,
      actor_type: actor_type,
      redirect_to: redirect_to,
      expires_at: now + expiry
    )
    link.save

    [ link, token ]
  end

  def self.find_by_token(token)
    find_by(token_digest: digest(token))
  end

  def self.digest(token)
    Digest::SHA256.hexdigest(token.to_s)
  end

  def self.expiry
    Rails.configuration.x.magic_links.expiry_minutes.minutes
  end

  def expired?(now = Time.current)
    now >= expires_at
  end

  def consumed?
    consumed_at.present?
  end

  def usable?(now = Time.current)
    !consumed? && !expired?(now)
  end

  def consume!(now = Time.current)
    update!(consumed_at: now)
  end
end
