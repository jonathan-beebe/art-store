class AddCancellationReasonToOrders < ActiveRecord::Migration[8.1]
  def change
    # Set only when an admin cancels: a customer's own cancel and the sweep
    # need no reason, so the column stays nil for those.
    add_column :orders, :cancellation_reason, :string
  end
end
