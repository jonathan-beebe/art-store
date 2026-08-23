class CreateMessages < ActiveRecord::Migration[8.1]
  def change
    # A conversation has exactly two sides, so one read_at per message is
    # unambiguous: the reader is the participant who did not send it.
    create_table :messages do |t|
      t.references :conversation, null: false, foreign_key: true, index: false
      t.references :sender, polymorphic: true, null: false
      t.text :body, null: false
      t.datetime :read_at

      t.timestamps
    end

    add_index :messages, [:conversation_id, :created_at]
  end
end
