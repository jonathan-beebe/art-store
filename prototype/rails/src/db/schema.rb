# This file is auto-generated from the current state of the database. Instead
# of editing this file, please use the migrations feature of Active Record to
# incrementally modify your database, and then regenerate this schema definition.
#
# This file is the source Rails uses to define your schema when running `bin/rails
# db:schema:load`. When creating a new database, `bin/rails db:schema:load` tends to
# be faster and is potentially less error prone than running all of your
# migrations from scratch. Old migrations may fail to apply correctly if those
# migrations use external dependencies or application code.
#
# It's strongly recommended that you check this file into your version control system.

ActiveRecord::Schema[8.1].define(version: 2026_08_22_000104) do
  create_table "customer_merges", force: :cascade do |t|
    t.integer "anonymous_customer_id", null: false
    t.datetime "created_at", null: false
    t.integer "customer_id", null: false
    t.datetime "updated_at", null: false
    t.index ["anonymous_customer_id"], name: "index_customer_merges_on_anonymous_customer_id", unique: true
    t.index ["customer_id"], name: "index_customer_merges_on_customer_id"
  end

  create_table "customers", force: :cascade do |t|
    t.datetime "created_at", null: false
    t.string "email"
    t.datetime "email_verified_at"
    t.string "name"
    t.datetime "updated_at", null: false
    t.index ["email"], name: "index_customers_on_email", unique: true
  end

  create_table "magic_links", force: :cascade do |t|
    t.string "actor_type", null: false
    t.datetime "consumed_at"
    t.datetime "created_at", null: false
    t.string "email", null: false
    t.datetime "expires_at", null: false
    t.string "redirect_to"
    t.string "token_digest", null: false
    t.datetime "updated_at", null: false
    t.index ["token_digest"], name: "index_magic_links_on_token_digest", unique: true
  end

  create_table "sellers", force: :cascade do |t|
    t.datetime "created_at", null: false
    t.string "email", null: false
    t.datetime "email_verified_at"
    t.string "name"
    t.string "shop_name"
    t.datetime "updated_at", null: false
    t.index ["email"], name: "index_sellers_on_email", unique: true
  end

  add_foreign_key "customer_merges", "customers"
  add_foreign_key "customer_merges", "customers", column: "anonymous_customer_id"
end
