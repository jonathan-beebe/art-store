class CreateMagicLinks < ActiveRecord::Migration[8.1]
  def change
    create_table :magic_links do |t|
      t.string :token_digest, null: false
      t.string :email, null: false
      t.string :actor_type, null: false
      t.string :redirect_to
      t.datetime :expires_at, null: false
      t.datetime :consumed_at

      t.timestamps
    end

    add_index :magic_links, :token_digest, unique: true
  end
end
