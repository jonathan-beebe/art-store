class AddUniquenessToListingFaqsSourceMessage < ActiveRecord::Migration[8.1]
  def change
    # A hand-written entry carries no source_message_id; SQLite does not treat
    # NULL as equal to NULL in a unique index, so any number of hand-written
    # entries stay allowed on one listing. Only a repeat publish of the same
    # answered message is refused at the row level.
    add_index :listing_faqs, %i[listing_id source_message_id], unique: true,
      name: "index_listing_faqs_on_listing_id_and_source_message_id"
  end
end
