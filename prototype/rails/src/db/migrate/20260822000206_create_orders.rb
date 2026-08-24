class CreateOrders < ActiveRecord::Migration[8.1]
  def change
    create_table :orders do |t|
      t.references :customer, null: false, foreign_key: true
      t.string :email
      t.string :status, null: false
      t.string :shipping_name, null: false
      t.string :shipping_line1, null: false
      t.string :shipping_line2
      t.string :shipping_city, null: false
      t.string :shipping_region, null: false
      t.string :shipping_postal_code, null: false
      t.string :shipping_country, null: false
      t.integer :subtotal_cents, null: false
      t.integer :total_cents, null: false
      t.datetime :placed_at, null: false
      t.datetime :finalized_at

      t.timestamps
    end

    add_index :orders, [ :customer_id, :placed_at ]
  end
end
