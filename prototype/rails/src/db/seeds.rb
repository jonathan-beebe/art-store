# This file should ensure the existence of records required to run the application in every environment (production,
# development, test). The code here should be idempotent so that it can be executed at any point in every environment.
# The data can then be loaded with the bin/rails db:seed command (or created alongside the database with db:setup).
require_relative "seeds/admins"
require_relative "seeds/sellers"
require_relative "seeds/listings"
require_relative "seeds/customers"
require_relative "seeds/order_history"
require_relative "seeds/messaging"

if Seller.exists?
  puts "Database already seeded, skipping."
else
  Seeds::Admins.create_all
  Seeds::Sellers.create_all
  Seeds::Listings.create_all
  Seeds::Customers.create_all
  Seeds::OrderHistory.create_all
  Seeds::Messaging.create_all

  puts "Seeded #{Admin.count} admins, #{Seller.count} sellers, #{Listing.count} listings, " \
       "#{Customer.count} customers, #{Order.count} orders, #{Conversation.count} conversations, " \
       "#{Message.count} messages, #{ListingFaq.count} published FAQ."
end
