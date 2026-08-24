require "test_helper"

class PrefixedIdTest < ActiveSupport::TestCase
  # The prefix each table mints under, from docs/alignment.md.
  PREFIXES = {
    Admin => "adm", Cart => "crt", CartItem => "cti", Conversation => "cnv",
    Customer => "cus", CustomerMerge => "cmg", Favorite => "fav", Fulfillment => "ful",
    LedgerEntry => "led", Listing => "lst", ListingEvent => "lev", ListingFaq => "faq",
    MagicLink => "mlk", Message => "msg", Notification => "ntf", Order => "ord",
    OrderItem => "oit", Payment => "pay", Payout => "pyt", Seller => "sel"
  }.freeze

  test "every table mints ids under its own prefix" do
    PREFIXES.each do |model, prefix|
      id = model.new.id

      assert_equal id, PrefixedUlid.parse(id, prefix), "#{model.name} mints no #{prefix} id"
    end
  end

  test "a row is stored and read back under the id it was built with" do
    seller = create_seller

    assert_equal seller.id, Seller.find(seller.id).id
  end
end
