class CreateSellers < ActiveRecord::Migration[8.1]
  def change
    create_table :sellers do |t|
      t.string :email, null: false
      t.string :name
      t.string :shop_name
      t.datetime :email_verified_at

      t.timestamps
    end

    add_index :sellers, :email, unique: true
  end
end
