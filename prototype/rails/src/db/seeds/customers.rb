# One verified customer with browsing history: views on several listings and
# three favorites, so the storefront's favorites page has content on first
# load.
module Seeds
  module Customers
    module_function

    HERMIONE_EMAIL = "hermione@example.com"
    VERIFIED_AT = Time.utc(2026, 6, 1)

    FAVORITE_TITLES = [
      "Divination Tower Vase, Tall",
      "The Orchard at First Light",
      "House Scarf Throw, Scarlet and Gold"
    ].freeze

    VIEWED_TITLES = [
      "Divination Tower Vase, Tall",
      "The Orchard at First Light",
      "House Scarf Throw, Scarlet and Gold",
      "Burrow Kitchen Tea Bowl",
      "Gryffindor Common Room, Late Morning",
      "Garden Gnome in Reclaimed Oak"
    ].freeze

    def create_all
      customer = Customer.create!(email: HERMIONE_EMAIL, name: "Hermione Granger", email_verified_at: VERIFIED_AT)

      record_views(customer)
      record_favorites(customer)

      customer
    end

    def record_views(customer)
      viewed_at = Time.utc(2026, 7, 1, 9, 0, 0)

      VIEWED_TITLES.each do |title|
        listing(title).record_event!("view", customer_id: customer.id, at: viewed_at)
        viewed_at += 1.minute
      end
    end

    def record_favorites(customer)
      favorited_at = Time.utc(2026, 7, 1, 9, 10, 0)

      FAVORITE_TITLES.each do |title|
        customer.toggle_favorite(listing(title), at: favorited_at)
        favorited_at += 1.minute
      end
    end

    def listing(title)
      Listing.find_by!(title: title)
    end
  end
end
