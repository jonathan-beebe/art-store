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

ActiveRecord::Schema[8.1].define(version: 2026_08_22_000212) do
  create_table "active_storage_attachments", force: :cascade do |t|
    t.bigint "blob_id", null: false
    t.datetime "created_at", null: false
    t.string "name", null: false
    t.bigint "record_id", null: false
    t.string "record_type", null: false
    t.index ["blob_id"], name: "index_active_storage_attachments_on_blob_id"
    t.index ["record_type", "record_id", "name", "blob_id"], name: "index_active_storage_attachments_uniqueness", unique: true
  end

  create_table "active_storage_blobs", force: :cascade do |t|
    t.bigint "byte_size", null: false
    t.string "checksum"
    t.string "content_type"
    t.datetime "created_at", null: false
    t.string "filename", null: false
    t.string "key", null: false
    t.text "metadata"
    t.string "service_name", null: false
    t.index ["key"], name: "index_active_storage_blobs_on_key", unique: true
  end

  create_table "active_storage_variant_records", force: :cascade do |t|
    t.bigint "blob_id", null: false
    t.string "variation_digest", null: false
    t.index ["blob_id", "variation_digest"], name: "index_active_storage_variant_records_uniqueness", unique: true
  end

  create_table "cart_items", force: :cascade do |t|
    t.integer "cart_id", null: false
    t.datetime "created_at", null: false
    t.integer "listing_id", null: false
    t.integer "quantity", null: false
    t.datetime "updated_at", null: false
    t.index ["cart_id", "listing_id"], name: "index_cart_items_on_cart_id_and_listing_id", unique: true
    t.index ["cart_id"], name: "index_cart_items_on_cart_id"
    t.index ["listing_id"], name: "index_cart_items_on_listing_id"
  end

  create_table "carts", force: :cascade do |t|
    t.datetime "created_at", null: false
    t.integer "customer_id", null: false
    t.datetime "updated_at", null: false
    t.index ["customer_id"], name: "index_carts_on_customer_id"
  end

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

  create_table "favorites", force: :cascade do |t|
    t.datetime "created_at", null: false
    t.integer "customer_id", null: false
    t.integer "listing_id", null: false
    t.datetime "updated_at", null: false
    t.index ["customer_id", "listing_id"], name: "index_favorites_on_customer_id_and_listing_id", unique: true
    t.index ["customer_id"], name: "index_favorites_on_customer_id"
    t.index ["listing_id"], name: "index_favorites_on_listing_id"
  end

  create_table "fulfillments", force: :cascade do |t|
    t.string "carrier"
    t.datetime "created_at", null: false
    t.datetime "delivered_at"
    t.integer "fee_cents", null: false
    t.integer "net_cents", null: false
    t.integer "order_id", null: false
    t.integer "seller_id", null: false
    t.datetime "shipped_at"
    t.string "status", default: "awaiting_shipment", null: false
    t.integer "subtotal_cents", null: false
    t.string "tracking_number"
    t.datetime "updated_at", null: false
    t.index ["order_id", "seller_id"], name: "index_fulfillments_on_order_id_and_seller_id", unique: true
    t.index ["order_id"], name: "index_fulfillments_on_order_id"
    t.index ["seller_id", "status"], name: "index_fulfillments_on_seller_id_and_status"
    t.index ["seller_id"], name: "index_fulfillments_on_seller_id"
  end

  create_table "ledger_entries", force: :cascade do |t|
    t.integer "amount_cents", null: false
    t.datetime "created_at", null: false
    t.string "entry_type", null: false
    t.integer "fulfillment_id"
    t.datetime "occurred_at", null: false
    t.integer "payout_id"
    t.integer "seller_id", null: false
    t.datetime "updated_at", null: false
    t.index ["fulfillment_id"], name: "index_ledger_entries_on_fulfillment_id"
    t.index ["payout_id"], name: "index_ledger_entries_on_payout_id"
    t.index ["seller_id", "entry_type", "occurred_at"], name: "idx_on_seller_id_entry_type_occurred_at_9fcdfd7522"
    t.index ["seller_id"], name: "index_ledger_entries_on_seller_id"
  end

  create_table "listing_events", force: :cascade do |t|
    t.datetime "created_at", null: false
    t.integer "customer_id"
    t.string "event_type", null: false
    t.integer "listing_id", null: false
    t.datetime "occurred_at", null: false
    t.datetime "updated_at", null: false
    t.index ["customer_id"], name: "index_listing_events_on_customer_id"
    t.index ["listing_id", "event_type"], name: "index_listing_events_on_listing_id_and_event_type"
    t.index ["listing_id"], name: "index_listing_events_on_listing_id"
  end

  create_table "listings", force: :cascade do |t|
    t.datetime "created_at", null: false
    t.text "description"
    t.string "dimensions"
    t.string "medium"
    t.integer "price_cents", null: false
    t.integer "quantity", default: 1, null: false
    t.integer "seller_id", null: false
    t.string "slug", null: false
    t.string "status", default: "draft", null: false
    t.string "title", null: false
    t.datetime "updated_at", null: false
    t.index ["seller_id"], name: "index_listings_on_seller_id"
    t.index ["slug"], name: "index_listings_on_slug", unique: true
    t.index ["status", "created_at"], name: "index_listings_on_status_and_created_at"
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

  create_table "notifications", force: :cascade do |t|
    t.text "body", null: false
    t.datetime "created_at", null: false
    t.integer "customer_id"
    t.datetime "read_at"
    t.integer "seller_id"
    t.string "subject", null: false
    t.datetime "updated_at", null: false
    t.string "url"
    t.index ["customer_id", "read_at"], name: "index_notifications_on_customer_id_and_read_at"
    t.index ["customer_id"], name: "index_notifications_on_customer_id"
    t.index ["seller_id", "read_at"], name: "index_notifications_on_seller_id_and_read_at"
    t.index ["seller_id"], name: "index_notifications_on_seller_id"
  end

  create_table "order_items", force: :cascade do |t|
    t.datetime "created_at", null: false
    t.integer "listing_id", null: false
    t.integer "order_id", null: false
    t.integer "quantity", null: false
    t.integer "seller_id", null: false
    t.string "title", null: false
    t.integer "unit_price_cents", null: false
    t.datetime "updated_at", null: false
    t.index ["listing_id"], name: "index_order_items_on_listing_id"
    t.index ["order_id"], name: "index_order_items_on_order_id"
    t.index ["seller_id"], name: "index_order_items_on_seller_id"
  end

  create_table "orders", force: :cascade do |t|
    t.datetime "created_at", null: false
    t.integer "customer_id", null: false
    t.string "email"
    t.datetime "finalized_at"
    t.datetime "placed_at", null: false
    t.string "shipping_city", null: false
    t.string "shipping_country", null: false
    t.string "shipping_line1", null: false
    t.string "shipping_line2"
    t.string "shipping_name", null: false
    t.string "shipping_postal_code", null: false
    t.string "shipping_region", null: false
    t.string "status", null: false
    t.integer "subtotal_cents", null: false
    t.integer "total_cents", null: false
    t.datetime "updated_at", null: false
    t.index ["customer_id", "placed_at"], name: "index_orders_on_customer_id_and_placed_at"
    t.index ["customer_id"], name: "index_orders_on_customer_id"
  end

  create_table "payments", force: :cascade do |t|
    t.integer "amount_cents", null: false
    t.string "card_last_four", null: false
    t.datetime "created_at", null: false
    t.string "decline_reason"
    t.integer "order_id", null: false
    t.datetime "processed_at", null: false
    t.string "status", null: false
    t.datetime "updated_at", null: false
    t.index ["order_id", "processed_at"], name: "index_payments_on_order_id_and_processed_at"
    t.index ["order_id"], name: "index_payments_on_order_id"
  end

  create_table "payouts", force: :cascade do |t|
    t.integer "amount_cents", null: false
    t.datetime "created_at", null: false
    t.datetime "paid_at", null: false
    t.date "period_end", null: false
    t.date "period_start", null: false
    t.integer "seller_id", null: false
    t.datetime "updated_at", null: false
    t.index ["seller_id", "period_start"], name: "index_payouts_on_seller_id_and_period_start", unique: true
    t.index ["seller_id"], name: "index_payouts_on_seller_id"
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

  add_foreign_key "active_storage_attachments", "active_storage_blobs", column: "blob_id"
  add_foreign_key "active_storage_variant_records", "active_storage_blobs", column: "blob_id"
  add_foreign_key "cart_items", "carts"
  add_foreign_key "cart_items", "listings"
  add_foreign_key "carts", "customers"
  add_foreign_key "customer_merges", "customers"
  add_foreign_key "customer_merges", "customers", column: "anonymous_customer_id"
  add_foreign_key "favorites", "customers"
  add_foreign_key "favorites", "listings"
  add_foreign_key "fulfillments", "orders"
  add_foreign_key "fulfillments", "sellers"
  add_foreign_key "ledger_entries", "fulfillments"
  add_foreign_key "ledger_entries", "payouts"
  add_foreign_key "ledger_entries", "sellers"
  add_foreign_key "listing_events", "customers"
  add_foreign_key "listing_events", "listings"
  add_foreign_key "listings", "sellers"
  add_foreign_key "notifications", "customers"
  add_foreign_key "notifications", "sellers"
  add_foreign_key "order_items", "listings"
  add_foreign_key "order_items", "orders"
  add_foreign_key "order_items", "sellers"
  add_foreign_key "orders", "customers"
  add_foreign_key "payments", "orders"
  add_foreign_key "payouts", "sellers"
end
