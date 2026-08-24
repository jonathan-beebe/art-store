class CreateRefunds < ActiveRecord::Migration[8.1]
  def change
    # One row per issue. A refund is always the whole fulfillment subtotal, so
    # the amount is a record of what was sent back rather than a decision the
    # row carries.
    create_table :refunds, id: :string do |t|
      t.references :order, null: false, foreign_key: true, type: :string
      t.references :fulfillment, null: false, foreign_key: true, type: :string
      t.references :payment, null: false, foreign_key: true, type: :string
      t.integer :amount_cents, null: false
      t.text :reason, null: false
      # A seller declining and an admin refunding write the same row; these two
      # columns say which of them did, and who.
      t.string :issued_by_type, null: false
      t.string :issued_by_id, null: false

      t.datetime :created_at, null: false
    end

    add_index :refunds, [ :issued_by_type, :issued_by_id ]
  end
end
