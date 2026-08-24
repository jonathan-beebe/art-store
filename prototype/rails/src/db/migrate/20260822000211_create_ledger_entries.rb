class CreateLedgerEntries < ActiveRecord::Migration[8.1]
  def change
    # amount_cents is signed: holds and releases are positive, a payout is
    # negative, so a seller's balance is the sum of the column.
    create_table :ledger_entries do |t|
      t.references :seller, null: false, foreign_key: true
      t.references :fulfillment, foreign_key: true
      t.references :payout, foreign_key: true
      t.string :entry_type, null: false
      t.integer :amount_cents, null: false
      t.datetime :occurred_at, null: false

      t.timestamps
    end

    add_index :ledger_entries, [ :seller_id, :entry_type, :occurred_at ]
  end
end
