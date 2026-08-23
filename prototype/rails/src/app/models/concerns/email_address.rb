# Addresses arrive from sign-in forms and from magic-link rows; both sides
# store the normalized form so one person never ends up with two accounts.
module EmailAddress
  extend ActiveSupport::Concern

  SHAPE = /\A[^@\s]+@[^@\s]+\.[^@\s]+\z/

  included do
    normalizes :email, with: ->(email) { email.strip.downcase }
  end
end
