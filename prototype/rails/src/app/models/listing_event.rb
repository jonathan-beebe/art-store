class ListingEvent < ApplicationRecord
  belongs_to :listing
  belongs_to :customer, optional: true

  enum :event_type, Domain::Listings::ListingEventType::ALL.index_by(&:to_sym)
end
