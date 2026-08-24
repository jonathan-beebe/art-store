class CreateCustomerBlocks < ActiveRecord::Migration[8.1]
  def change
    # An unlifted row here turns off `Customer#can_shop?`. Browsing stays
    # open; the row exists to be read as a reason, not as a ban.
    create_table :customer_blocks, id: :string do |t|
      t.references :customer, null: false, foreign_key: true, type: :string
      t.references :admin, null: false, foreign_key: true, type: :string
      t.text :reason, null: false
      t.datetime :lifted_at

      t.timestamps
    end

    # At most one active block per customer, the same partial-index shape as
    # `listing_removals`.
    add_index :customer_blocks, :customer_id, unique: true, where: "lifted_at IS NULL",
      name: "index_customer_blocks_on_customer_id_while_active"
  end
end
