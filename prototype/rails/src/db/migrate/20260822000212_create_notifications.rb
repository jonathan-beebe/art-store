class CreateNotifications < ActiveRecord::Migration[8.1]
  def change
    # Exactly one recipient is set. Two columns rather than a type and an id
    # keep the foreign keys real and let a customer merge re-point rows.
    create_table :notifications do |t|
      t.references :seller, foreign_key: true
      t.references :customer, foreign_key: true
      t.string :subject, null: false
      t.text :body, null: false
      t.string :url
      t.datetime :read_at

      t.timestamps
    end

    add_index :notifications, [ :seller_id, :read_at ]
    add_index :notifications, [ :customer_id, :read_at ]
  end
end
