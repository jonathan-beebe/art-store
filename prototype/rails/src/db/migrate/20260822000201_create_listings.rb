class CreateListings < ActiveRecord::Migration[8.1]
  def change
    create_table :listings do |t|
      t.references :seller, null: false, foreign_key: true
      t.string :title, null: false
      t.string :slug, null: false
      t.text :description
      t.integer :price_cents, null: false
      t.integer :quantity, null: false, default: 1
      t.string :status, null: false, default: "draft"
      t.string :medium
      t.string :dimensions

      t.timestamps
    end

    add_index :listings, :slug, unique: true
    add_index :listings, [ :status, :created_at ]
  end
end
