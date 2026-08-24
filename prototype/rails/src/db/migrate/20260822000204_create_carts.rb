class CreateCarts < ActiveRecord::Migration[8.1]
  def change
    create_table :carts, id: :string do |t|
      t.references :customer, null: false, foreign_key: true, type: :string

      t.timestamps
    end
  end
end
