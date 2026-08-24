class CreateCustomers < ActiveRecord::Migration[8.1]
  def change
    # A visitor gets a row before they give an address, so email is nullable
    # and the unique index only constrains the rows that carry one.
    create_table :customers, id: :string do |t|
      t.string :email
      t.string :name
      t.datetime :email_verified_at

      t.timestamps
    end

    add_index :customers, :email, unique: true
  end
end
