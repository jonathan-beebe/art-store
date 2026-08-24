class CreateAdmins < ActiveRecord::Migration[8.1]
  def change
    create_table :admins, id: :string do |t|
      t.string :email, null: false
      t.string :name
      t.datetime :email_verified_at

      t.timestamps
    end

    add_index :admins, :email, unique: true
  end
end
