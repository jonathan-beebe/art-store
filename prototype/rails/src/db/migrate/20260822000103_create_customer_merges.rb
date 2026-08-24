class CreateCustomerMerges < ActiveRecord::Migration[8.1]
  def change
    # The trail a merge leaves behind: a cookie still holding the anonymous id
    # resolves forward to the customer that absorbed it.
    create_table :customer_merges, id: :string do |t|
      t.references :anonymous_customer, null: false, index: { unique: true },
        foreign_key: { to_table: :customers }, type: :string
      t.references :customer, null: false, foreign_key: true, type: :string

      t.timestamps
    end
  end
end
