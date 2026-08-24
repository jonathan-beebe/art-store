class AddUniqueIndexToRefundsFulfillmentId < ActiveRecord::Migration[8.1]
  def change
    # At most one refund per fulfillment (declined and refunded are both
    # terminal, so a second issue is already refused by the transition guard
    # above the database); the unique index is the same rule enforced at the
    # row level, matching the PHP prototype.
    remove_index :refunds, :fulfillment_id
    add_index :refunds, :fulfillment_id, unique: true
  end
end
