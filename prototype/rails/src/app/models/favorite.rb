class Favorite < ApplicationRecord
  prefixed_id :fav

  belongs_to :customer
  belongs_to :listing
end
