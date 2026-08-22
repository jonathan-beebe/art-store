class CreateOrderItems < ActiveRecord::Migration[8.1]
  def change
    # Title and unit price are snapshots: an order reads the same after the
    # seller edits the listing behind it.
    create_table :order_items do |t|
      t.references :order, null: false, foreign_key: true
      t.references :listing, null: false, foreign_key: true
      t.references :seller, null: false, foreign_key: true
      t.string :title, null: false
      t.integer :unit_price_cents, null: false
      t.integer :quantity, null: false

      t.timestamps
    end
  end
end
