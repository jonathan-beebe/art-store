class Seller < ApplicationRecord
  normalizes :email, with: ->(email) { Domain::Auth::EmailAddress.normalize(email) }
end
