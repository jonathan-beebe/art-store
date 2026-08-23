class AddShapeUniquenessToConversations < ActiveRecord::Migration[8.1]
  def change
    # One thread per kind, participants and subject. Each kind leaves some of
    # the six columns null and SQLite counts two nulls as different values, so
    # the index reads every column through COALESCE and a second row of the
    # same shape collides whichever kind it is.
    add_index :conversations,
      "kind, COALESCE(seller_id, 0), COALESCE(customer_id, 0), COALESCE(admin_id, 0), " \
      "COALESCE(subject_type, ''), COALESCE(subject_id, 0)",
      unique: true, name: "index_conversations_on_shape"
  end
end
