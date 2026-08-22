# Four verified sellers a reviewer can sign in as through the debug magic
# link on first load.
module Seeds
  module Sellers
    module_function

    VERIFIED_AT = Time.utc(2026, 6, 1)

    RECORDS = [
      { email: "maya@example.com", name: "Maya Reyes", shop_name: "Terra & Glaze Ceramics" },
      { email: "noah@example.com", name: "Noah Chen", shop_name: "North Light Editions" },
      { email: "priya@example.com", name: "Priya Anand", shop_name: "Priya Anand Textile Studio" },
      { email: "leo@example.com", name: "Leo Martins", shop_name: "Leo Martins Photography" }
    ].freeze

    def create_all
      RECORDS.each { |attrs| Seller.create!(attrs.merge(email_verified_at: VERIFIED_AT)) }
    end
  end
end
