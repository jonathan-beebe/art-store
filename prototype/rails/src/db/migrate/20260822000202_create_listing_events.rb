class CreateListingEvents < ActiveRecord::Migration[8.1]
  def change
    create_table :listing_events, id: :string do |t|
      t.references :listing, null: false, foreign_key: true, type: :string
      t.references :customer, foreign_key: true, type: :string
      t.string :event_type, null: false
      t.datetime :occurred_at, null: false

      t.timestamps
    end

    add_index :listing_events, [ :listing_id, :event_type ]
  end
end
