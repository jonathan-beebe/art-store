class Notification < ApplicationRecord
  RECIPIENT_COLUMNS = {
    Domain::Notifications::RecipientType::SELLER => :seller_id,
    Domain::Notifications::RecipientType::CUSTOMER => :customer_id
  }.freeze

  belongs_to :seller, optional: true
  belongs_to :customer, optional: true

  scope :unread, -> { where(read_at: nil) }

  def self.recipient_column(recipient_type)
    RECIPIENT_COLUMNS.fetch(recipient_type)
  end
end
