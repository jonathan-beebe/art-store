class CreateListingFaqs < ActiveRecord::Migration[8.1]
  def change
    # A row exists only while the entry is published, so the storefront reads
    # the table with no predicate of its own.
    create_table :listing_faqs, id: :string do |t|
      t.references :listing, null: false, foreign_key: true, index: false, type: :string
      t.text :question, null: false
      t.text :answer, null: false
      # The answer the entry was lifted from. The entry outlives the thread.
      t.references :source_message,
        foreign_key: { to_table: :messages, on_delete: :nullify },
        index: false, type: :string
      t.datetime :published_at, null: false

      t.timestamps
    end

    add_index :listing_faqs, [ :listing_id, :created_at ]
  end
end
