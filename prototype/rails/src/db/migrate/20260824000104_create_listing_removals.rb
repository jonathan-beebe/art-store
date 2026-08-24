class CreateListingRemovals < ActiveRecord::Migration[8.1]
  def change
    # Whatever a listing's own status says, an unlifted row here takes it off
    # the storefront. `kind` decides whether `lifted_at` can ever be set.
    create_table :listing_removals, id: :string do |t|
      t.references :listing, null: false, foreign_key: true, type: :string
      t.references :admin, null: false, foreign_key: true, type: :string
      t.string :kind, null: false
      t.text :reason, null: false
      t.datetime :lifted_at

      t.timestamps
    end

    # At most one active removal per listing: the partial index covers only
    # the unlifted rows, so a lifted one and a fresh removal never collide.
    add_index :listing_removals, :listing_id, unique: true, where: "lifted_at IS NULL",
      name: "index_listing_removals_on_listing_id_while_active"
  end
end
