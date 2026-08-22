class CreatePayments < ActiveRecord::Migration[8.1]
  def change
    # One row per charge attempt, so a decline and the retry that follows it
    # both stay on the order.
    create_table :payments do |t|
      t.references :order, null: false, foreign_key: true
      t.string :status, null: false
      t.integer :amount_cents, null: false
      t.string :card_last_four, null: false
      t.string :decline_reason
      t.datetime :processed_at, null: false

      t.timestamps
    end

    add_index :payments, [:order_id, :processed_at]
  end
end
