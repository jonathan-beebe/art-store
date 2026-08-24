class CreateFavorites < ActiveRecord::Migration[8.1]
  def change
    create_table :favorites, id: :string do |t|
      t.references :customer, null: false, foreign_key: true, type: :string
      t.references :listing, null: false, foreign_key: true, type: :string

      t.timestamps
    end

    add_index :favorites, [ :customer_id, :listing_id ], unique: true
  end
end
