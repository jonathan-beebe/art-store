class CreateFulfillments < ActiveRecord::Migration[8.1]
  def change
    # An order that spans sellers ships and settles once per seller.
    create_table :fulfillments do |t|
      t.references :order, null: false, foreign_key: true
      t.references :seller, null: false, foreign_key: true
      t.string :status, null: false, default: "awaiting_shipment"
      t.string :carrier
      t.string :tracking_number
      t.datetime :shipped_at
      t.datetime :delivered_at
      t.integer :subtotal_cents, null: false
      t.integer :fee_cents, null: false
      t.integer :net_cents, null: false

      t.timestamps
    end

    add_index :fulfillments, [ :order_id, :seller_id ], unique: true
    add_index :fulfillments, [ :seller_id, :status ]
  end
end
