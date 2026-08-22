class MagicLink < ApplicationRecord
  normalizes :email, with: ->(email) { Domain::Auth::EmailAddress.normalize(email) }

  scope :for_token, ->(token) { where(token_digest: Domain::Auth::MagicLinkToken.digest(token)) }

  def actor_type
    Domain::Auth::ActorType.named(self[:actor_type])
  end

  def actor_type=(actor_type)
    super(actor_type.to_s)
  end

  def status_at(now)
    Domain::Auth::MagicLinkStatus.of(expires_at: expires_at, consumed_at: consumed_at, now: now)
  end

  def consume!(now)
    update!(consumed_at: now)
  end
end
