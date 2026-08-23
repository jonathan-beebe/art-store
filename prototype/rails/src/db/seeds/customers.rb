# One verified customer with browsing history: views on several listings and
# three favorites, so the storefront's favorites page has content on first
# load.
module Seeds
  module Customers
    module_function

    CASEY_EMAIL = "casey@example.com"
    VERIFIED_AT = Time.utc(2026, 6, 1)

    FAVORITE_TITLES = [
      "Woodfired Vase, Tall",
      "Quarry at First Light",
      "Handwoven Mohair Throw"
    ].freeze

    VIEWED_TITLES = [
      "Woodfired Vase, Tall",
      "Quarry at First Light",
      "Handwoven Mohair Throw",
      "Ash-Glazed Tea Bowl",
      "Kitchen Table, Late Morning",
      "Standing Figure in Reclaimed Oak"
    ].freeze

    def create_all
      customer = Customer.create!(email: CASEY_EMAIL, name: "Casey Whitfield", email_verified_at: VERIFIED_AT)

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
