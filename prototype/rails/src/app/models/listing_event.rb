class ListingEvent < ApplicationRecord
  belongs_to :listing
  belongs_to :customer, optional: true

  enum :event_type, { view: "view", favorite: "favorite", unfavorite: "unfavorite", cart_add: "cart_add" }
end
