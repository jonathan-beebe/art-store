class CreatePageViewCounts < ActiveRecord::Migration[8.1]
  def change
    create_table :page_view_counts, id: :string do |t|
      t.string :site, null: false
      t.string :path_pattern, null: false
      t.date :day, null: false
      t.integer :count, null: false, default: 0

      t.timestamps
    end

    add_index :page_view_counts, [ :site, :path_pattern, :day ], unique: true,
      name: "index_page_view_counts_on_site_and_path_pattern_and_day"
  end
end
