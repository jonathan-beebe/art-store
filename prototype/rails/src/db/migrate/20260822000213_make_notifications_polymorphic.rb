class MakeNotificationsPolymorphic < ActiveRecord::Migration[8.1]
  def up
    add_column :notifications, :recipient_type, :string
    add_column :notifications, :recipient_id, :integer

    execute "UPDATE notifications SET recipient_type = 'Seller', recipient_id = seller_id WHERE seller_id IS NOT NULL"
    execute "UPDATE notifications SET recipient_type = 'Customer', recipient_id = customer_id WHERE customer_id IS NOT NULL"

    change_column_null :notifications, :recipient_type, false
    change_column_null :notifications, :recipient_id, false
    add_index :notifications, [ :recipient_type, :recipient_id, :read_at ]

    # SQLite rebuilds the table around a dropped column and carries a composite
    # index over as its remaining half, so the pair goes first.
    remove_index :notifications, [ :seller_id, :read_at ]
    remove_index :notifications, [ :customer_id, :read_at ]
    remove_column :notifications, :seller_id
    remove_column :notifications, :customer_id
  end

  def down
    add_reference :notifications, :seller, foreign_key: true
    add_reference :notifications, :customer, foreign_key: true
    add_index :notifications, [ :seller_id, :read_at ]
    add_index :notifications, [ :customer_id, :read_at ]

    execute "UPDATE notifications SET seller_id = recipient_id WHERE recipient_type = 'Seller'"
    execute "UPDATE notifications SET customer_id = recipient_id WHERE recipient_type = 'Customer'"

    remove_index :notifications, [ :recipient_type, :recipient_id, :read_at ]
    remove_column :notifications, :recipient_type
    remove_column :notifications, :recipient_id
  end
end
