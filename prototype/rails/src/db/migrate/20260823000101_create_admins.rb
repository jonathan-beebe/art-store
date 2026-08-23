class CreateAdmins < ActiveRecord::Migration[8.1]
  def change
    create_table :admins do |t|
      t.string :email, null: false
      t.string :name
      t.datetime :email_verified_at

      t.timestamps
    end

    add_index :admins, :email, unique: true
  end
end
