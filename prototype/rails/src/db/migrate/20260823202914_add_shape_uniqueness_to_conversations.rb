class AddShapeUniquenessToConversations < ActiveRecord::Migration[8.1]
  def change
    # One thread per kind, participants and subject. Each kind leaves some of
    # the six columns null and SQLite counts two nulls as different values, so
    # the index reads every column through COALESCE and a second row of the
    # same shape collides whichever kind it is.
    add_index :conversations,
      "kind, COALESCE(seller_id, ''), COALESCE(customer_id, ''), COALESCE(admin_id, ''), " \
      "COALESCE(subject_type, ''), COALESCE(subject_id, '')",
      unique: true, name: "index_conversations_on_shape"
  end
end
