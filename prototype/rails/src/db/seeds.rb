# This file should ensure the existence of records required to run the application in every environment (production,
# development, test). The code here should be idempotent so that it can be executed at any point in every environment.
# The data can then be loaded with the bin/rails db:seed command (or created alongside the database with db:setup).
require "active_support/testing/time_helpers"
require_relative "seeds/admins"
require_relative "seeds/sellers"
require_relative "seeds/listings"
require_relative "seeds/customers"
require_relative "seeds/order_history"
require_relative "seeds/messaging"
require_relative "seeds/wizarding_sellers"

extend ActiveSupport::Testing::TimeHelpers

if Seller.exists?
  puts "Database already seeded, skipping."
else
  # The instant every row's created_at and minted id come from. The narrative
  # dates each seed module passes as `at:` (placed_at, shipped_at, the message
  # exchange, ...) stay whatever they are; this is only the clock
  # `Time.current` reads while the run mints ids and stamps created_at, so two
  # runs mint ids that land in the same relative order and stay reproducible
  # in time order.
  seeded_at = Time.utc(2026, 7, 20, 8, 0, 0)

  travel_to(seeded_at) do
    Seeds::Admins.create_all
    Seeds::Sellers.create_all
    Seeds::Listings.create_all
    Seeds::Customers.create_all
    Seeds::OrderHistory.create_all
    Seeds::Messaging.create_all
  end

  puts "Seeded #{Admin.count} admins, #{Seller.count} sellers, #{Listing.count} listings, " \
       "#{Customer.count} customers, #{Order.count} orders, #{Conversation.count} conversations, " \
       "#{Message.count} messages, #{ListingFaq.count} published FAQ."
end

# After the demo data: its already-seeded check reads "any seller exists", so
# seeding these two first would skip the whole demo. Guarded on its own first
# email, so the wizarding sellers land even on a database the demo seed
# refuses to re-touch.
wizarding = travel_to(Time.utc(2026, 8, 21)) { Seeds::WizardingSellers.create_all }

if wizarding.nil?
  puts "Wizarding sellers already seeded, skipping."
else
  puts "Seeded #{wizarding.fetch(:seller_count)} wizarding sellers, #{wizarding.fetch(:listing_count)} listings."
end
