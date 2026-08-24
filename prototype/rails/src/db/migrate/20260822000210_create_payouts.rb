class CreatePayouts < ActiveRecord::Migration[8.1]
  def change
    create_table :payouts, id: :string do |t|
      t.references :seller, null: false, foreign_key: true, type: :string
      t.date :period_start, null: false
      t.date :period_end, null: false
      t.integer :amount_cents, null: false
      t.datetime :paid_at, null: false

      t.timestamps
    end

    add_index :payouts, [ :seller_id, :period_start ], unique: true
  end
end
