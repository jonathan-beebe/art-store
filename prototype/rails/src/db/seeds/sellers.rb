# Four verified sellers a reviewer can sign in as through the debug magic
# link on first load.
module Seeds
  module Sellers
    module_function

    VERIFIED_AT = Time.utc(2026, 6, 1)

    RECORDS = [
      { email: "molly@example.com", name: "Molly Weasley", shop_name: "The Burrow Craftworks" },
      { email: "dean@example.com", name: "Dean Thomas", shop_name: "Dean Thomas Studio" },
      { email: "sybill@example.com", name: "Sybill Trelawney", shop_name: "Trelawney's Tower Studio" },
      { email: "colin@example.com", name: "Colin Creevey", shop_name: "Creevey Camera Works" }
    ].freeze

    def create_all
      RECORDS.each { |attrs| Seller.create!(attrs.merge(email_verified_at: VERIFIED_AT)) }
    end
  end
end
