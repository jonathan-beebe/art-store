class CreateConversations < ActiveRecord::Migration[8.1]
  def change
    # One table serves every pairing: `kind` says which two participant columns
    # are filled and what the subject, if any, points at.
    create_table :conversations do |t|
      t.string :kind, null: false
      t.references :seller, foreign_key: true, index: false
      t.references :customer, foreign_key: true, index: false
      t.references :admin, foreign_key: true, index: false
      t.references :subject, polymorphic: true, index: false
      t.datetime :last_message_at, null: false

      t.timestamps
    end

    # An inbox reads one participant column newest first.
    add_index :conversations, [:seller_id, :last_message_at]
    add_index :conversations, [:customer_id, :last_message_at]
    add_index :conversations, [:admin_id, :last_message_at]

    # Find-or-open reads the thread on a subject.
    add_index :conversations, [:kind, :subject_type, :subject_id]
  end
end
