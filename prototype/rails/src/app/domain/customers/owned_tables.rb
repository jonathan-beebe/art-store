module Domain
  module Customers
    # Tables whose rows move with the customer when an anonymous identity is
    # merged into a verified one: table name => column holding the customer id.
    module OwnedTables
      ALL = {
        "favorites" => "customer_id",
        "carts" => "customer_id",
        "orders" => "customer_id",
        "listing_events" => "customer_id",
        "notifications" => "customer_id"
      }.freeze
    end
  end
end
