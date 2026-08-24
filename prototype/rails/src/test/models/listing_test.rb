require "test_helper"

class ListingTest < ActiveSupport::TestCase
  test "a filled in listing is valid" do
    assert_predicate draft, :valid?
  end

  test "a title is required" do
    record = draft(title: "   ")

    refute_predicate record, :valid?
    assert_equal "Enter a title.", record.errors[:title].first
  end

  test "a title has a length limit" do
    record = draft(title: "a" * 256)

    refute_predicate record, :valid?
    assert_equal "Keep the title under 255 characters.", record.errors[:title].first
  end

  test "a description has a length limit" do
    record = draft(description: "a" * 5_001)

    refute_predicate record, :valid?
    assert_equal "Keep the description under 5000 characters.", record.errors[:description].first
  end

  test "the price is an amount in dollars" do
    message = "The price is an amount in dollars, like 249.00."

    [ "free", "$249", "249.005", "" ].each do |typed|
      record = draft(price: typed)

      refute_predicate record, :valid?
      assert_equal message, record.errors[:price].first
    end
  end

  test "a whole dollar price needs no decimals" do
    assert_predicate draft(price: "249"), :valid?
  end

  test "the quantity is a whole number within range" do
    message = "The quantity is a whole number from 0 to 999."

    [ "-1", "1.5", "1000" ].each do |typed|
      record = draft(quantity: typed)

      refute_predicate record, :valid?
      assert_equal message, record.errors[:quantity].first
    end
  end

  test "a sold out edition may be zero" do
    assert_predicate draft(quantity: "0"), :valid?
  end

  test "an upload that is not an image is refused" do
    record = draft
    record.image = uploaded_pdf

    refute_predicate record, :valid?
    assert_equal "Upload an image file.", record.errors[:image].first
  end

  test "an image upload is accepted" do
    record = draft
    record.image = uploaded_image("harbour.png")

    assert_predicate record, :valid?
  end

  test "a JPEG, GIF, and WebP upload are each accepted" do
    [
      upload("image/jpeg", "\xff\xd8\xff\xe0\x00\x10", "harbour.jpg"),
      upload("image/gif", "GIF89a rest of file", "harbour.gif"),
      upload("image/webp", "RIFF____WEBPVP8 ", "harbour.webp")
    ].each do |file|
      record = draft
      record.image = file

      assert_predicate record, :valid?
    end
  end

  test "an SVG upload is refused whatever its declared content type" do
    record = draft
    record.image = uploaded_svg

    refute_predicate record, :valid?
    assert_equal "Upload an image file.", record.errors[:image].first
  end

  test "an SVG upload declared as a PNG is still refused — the bytes decide" do
    record = draft
    record.image = upload("image/png", "<svg xmlns=\"http://www.w3.org/2000/svg\"></svg>", "harbour.png")

    refute_predicate record, :valid?
    assert_equal "Upload an image file.", record.errors[:image].first
  end

  test "a real PNG declared as text/plain is accepted on its bytes" do
    record = draft
    record.image = upload("text/plain", "\x89PNG\r\n\x1a\n", "harbour.txt")

    assert_predicate record, :valid?
  end

  test "an upload over the size cap is refused with a field error" do
    record = draft
    record.image = image_of_size(Listing::MAX_IMAGE_UPLOAD_BYTES + 1)

    refute_predicate record, :valid?
    assert_equal "Upload an image under 5 MB.", record.errors[:image].first
  end

  test "an upload at exactly the size cap is accepted" do
    record = draft
    record.image = image_of_size(Listing::MAX_IMAGE_UPLOAD_BYTES)

    assert_predicate record, :valid?
  end

  test "a listing with no upload asks for none" do
    assert_empty draft.errors[:image]
  end

  test "it stores the price in cents" do
    assert_equal 24_900, draft(price: "249.00").price_cents
    assert_equal 24_900, draft(price: "249").price_cents
    assert_equal 30_050, draft(price: "300.50").price_cents
  end

  test "it reads the price back in dollars" do
    assert_equal "450.00", create_listing(price_cents: 45_000).price
  end

  test "a price it refuses is rendered back as the seller typed it" do
    assert_equal "free", draft(price: "free").price
  end

  test "it trims the text a seller typed" do
    assert_equal "Harbour at Dusk", draft(title: "  Harbour at Dusk  ").title
  end

  test "a field left blank is stored as nothing" do
    record = draft(description: "", medium: "   ", dimensions: "")

    assert_nil record.description
    assert_nil record.medium
    assert_nil record.dimensions
  end

  test "a new listing starts as a draft" do
    assert_predicate Listing.new, :draft?
  end

  test "it slugs the title" do
    assert_equal "harbour-at-dusk", created(title: "Harbour at Dusk").slug
  end

  test "it drops punctuation and transliterates" do
    assert_equal "study-no-4", created(title: "Study, No. 4!").slug
    assert_equal "cafe-window", created(title: "Café Window").slug
  end

  test "a title another listing already slugged is numbered" do
    3.times { created(title: "Harbour at Dusk") }

    assert_equal %w[harbour-at-dusk harbour-at-dusk-2 harbour-at-dusk-3], Listing.order(:id).pluck(:slug)
  end

  test "it ignores slugs another title holds" do
    created(title: "Morning Tide")

    assert_equal "harbour-at-dusk", created(title: "Harbour at Dusk").slug
  end

  test "a title that slugs to nothing falls back to a word" do
    assert_equal "listing", created(title: "—").slug
  end

  test "a retitled listing keeps the storefront URL it was shared under" do
    record = created(title: "Harbour at Dusk")

    record.update!(title: "Harbour at Dawn")

    assert_equal "harbour-at-dusk", record.reload.slug
  end

  test "a draft goes on sale" do
    record = create_listing(status: :draft)

    record.transition_to!("for_sale")

    assert_predicate record.reload, :for_sale?
  end

  test "a move the lifecycle refuses raises and changes nothing" do
    record = create_listing(status: :draft)

    error = assert_raises(TransitionError) { record.transition_to!("sold") }

    assert_equal "A listing cannot move from draft to sold.", error.message
    assert_predicate record.reload, :draft?
  end

  test "a status the lifecycle does not name is refused" do
    assert_raises(TransitionError) { create_listing(status: :draft).transition_to!("on_loan") }
  end

  test "it offers the moves the lifecycle allows" do
    assert_equal %w[for_sale archived], create_listing(status: :draft).next_statuses
    assert_equal %w[sold archived], create_listing(status: :for_sale).next_statuses
    assert_equal %w[for_sale], create_listing(status: :sold, quantity: 0).next_statuses
    assert_empty create_listing(status: :archived).next_statuses
  end

  test "a listing on sale with stock is purchasable" do
    assert_predicate create_listing(status: :for_sale, quantity: 1), :purchasable?
    refute_predicate create_listing(status: :for_sale, quantity: 0), :purchasable?
    refute_predicate create_listing(status: :sold, quantity: 3), :purchasable?
  end

  test "a sale takes the quantity it asks for" do
    record = create_listing(status: :for_sale, quantity: 3)

    record.take_stock!(2)

    assert_equal 1, record.quantity
    assert_equal "for_sale", record.status
  end

  test "the last of a listing marks it sold" do
    record = create_listing(status: :for_sale, quantity: 1)

    record.take_stock!(1)

    assert_equal 0, record.quantity
    assert_equal "sold", record.status
  end

  test "a sale refuses to take more than is left" do
    assert_raises(ArgumentError) { create_listing(status: :for_sale, quantity: 1).take_stock!(2) }
  end

  test "a sale refuses a listing that is not for sale" do
    assert_raises(ArgumentError) { create_listing(status: :draft, quantity: 1).take_stock!(1) }
  end

  test "a sale covers at least one item" do
    error = assert_raises(ArgumentError) { create_listing(status: :for_sale, quantity: 3).take_stock!(0) }

    assert_equal "a stock change covers at least one item, got 0", error.message
  end

  test "a restock puts a sold listing back on the storefront" do
    record = create_listing(status: :sold, quantity: 0)

    record.restore_stock!(1)

    assert_equal 1, record.quantity
    assert_equal "for_sale", record.status
  end

  test "a restock leaves a listing that is still for sale alone" do
    record = create_listing(status: :for_sale, quantity: 2)

    record.restore_stock!(1)

    assert_equal 3, record.quantity
    assert_equal "for_sale", record.status
  end

  test "a restock covers at least one item" do
    assert_raises(ArgumentError) { create_listing(status: :sold, quantity: 0).restore_stock!(0) }
  end

  test "the storefront carries listings for sale and sold" do
    for_sale = create_listing(status: :for_sale)
    sold = create_listing(status: :sold, quantity: 0)
    create_listing(status: :draft)
    create_listing(status: :archived)

    assert_equal [ for_sale, sold ].map(&:id).sort, Listing.on_storefront.pluck(:id).sort
  end

  test "a removal takes a listing off the storefront whatever its status" do
    record = create_listing(status: :for_sale)
    record.remove!(kind: :temporary, reason: "Reported as counterfeit", by: create_admin)

    assert_empty Listing.on_storefront.where(id: record.id)
    refute_predicate record, :on_storefront?
    refute_predicate record, :purchasable?
  end

  test "removing a listing names the reason and who removed it" do
    admin = create_admin
    record = create_listing

    removal = record.remove!(kind: :permanent, reason: "Counterfeit artwork.", by: admin)

    assert_equal "permanent", removal.kind
    assert_equal "Counterfeit artwork.", removal.reason
    assert_equal admin, removal.admin
    assert_predicate record, :actively_removed?
    assert_equal removal, record.active_removal
  end

  test "a listing already removed is not removed a second time" do
    record = create_listing
    record.remove!(kind: :temporary, reason: "First report.", by: create_admin)

    error = assert_raises(TransitionError) do
      record.remove!(kind: :permanent, reason: "Second report.", by: create_admin)
    end

    assert_equal "listing #{record.id} is already removed", error.message
    assert_equal 1, record.removals.count
  end

  test "lifting a temporary removal puts the listing back on the storefront" do
    record = create_listing(status: :for_sale)
    record.remove!(kind: :temporary, reason: "Retake the photograph.", by: create_admin)

    lifted = record.lift_removal!

    refute_nil lifted.lifted_at
    refute_predicate record, :actively_removed?
    assert_predicate record, :on_storefront?
  end

  test "a permanent removal is refused, and the listing stays off" do
    record = create_listing
    record.remove!(kind: :permanent, reason: "Counterfeit.", by: create_admin)

    error = assert_raises(TransitionError) { record.lift_removal! }

    assert_equal "a permanent removal cannot be lifted", error.message
    assert_predicate record, :actively_removed?
  end

  test "a listing nobody removed cannot be lifted" do
    record = create_listing

    error = assert_raises(TransitionError) { record.lift_removal! }

    assert_equal "listing #{record.id} is not removed", error.message
  end

  test "a removal drops for_sale from the moves the seller can make" do
    record = create_listing(status: :draft)
    record.remove!(kind: :temporary, reason: "Under review.", by: create_admin)

    assert_equal [ "archived" ], record.next_statuses
  end

  test "the seller cannot put a removed listing back on sale" do
    record = create_listing(status: :draft)
    record.remove!(kind: :temporary, reason: "Under review.", by: create_admin)

    error = assert_raises(TransitionError) { record.transition_to!("for_sale") }

    assert_equal "This listing was removed by an admin and cannot be put back on sale.", error.message
    assert_predicate record.reload, :draft?
  end

  test "Listing.removed carries only the listings a removal stands over" do
    removed = create_listing
    removed.remove!(kind: :temporary, reason: "Reported.", by: create_admin)
    untouched = create_listing

    assert_equal [ removed.id ], Listing.removed.pluck(:id)
    assert_equal [ untouched.id ], Listing.visible.pluck(:id)
  end

  test "a lifted removal leaves a listing visible again" do
    record = create_listing
    record.remove!(kind: :temporary, reason: "Reported.", by: create_admin)
    record.lift_removal!

    assert_equal [ record.id ], Listing.visible.pluck(:id)
    assert_empty Listing.removed.where(id: record.id)
  end

  test "it records what happened and when" do
    record = create_listing
    shopper = create_verified_customer

    event = record.record_event!("view", customer_id: shopper.id, at: moment("2026-08-20 08:00:00"))

    assert_equal record, event.listing
    assert_equal "view", event.event_type
    assert_equal shopper.id, event.customer_id
    assert_equal moment("2026-08-20 08:00:00"), event.occurred_at
  end

  test "an anonymous visitor leaves an event with no customer" do
    assert_nil create_listing.record_event!("view").customer_id
  end

  test "a second view from the same customer in the same UTC hour is collapsed" do
    record = create_listing
    shopper = create_verified_customer
    record.record_event!("view", customer_id: shopper.id, at: moment("2026-08-20 08:03:00"))

    collapsed = record.record_event!("view", customer_id: shopper.id, at: moment("2026-08-20 08:57:00"))

    assert_nil collapsed
    assert_equal 1, record.events.where(event_type: "view").count
  end

  test "a view in the next UTC hour writes its own row" do
    record = create_listing
    shopper = create_verified_customer
    record.record_event!("view", customer_id: shopper.id, at: moment("2026-08-20 08:57:00"))

    second = record.record_event!("view", customer_id: shopper.id, at: moment("2026-08-20 09:03:00"))

    refute_nil second
    assert_equal 2, record.events.where(event_type: "view").count
  end

  test "a different customer in the same hour writes their own view" do
    record = create_listing
    first_shopper = create_verified_customer
    second_shopper = create_verified_customer
    record.record_event!("view", customer_id: first_shopper.id, at: moment("2026-08-20 08:03:00"))

    second = record.record_event!("view", customer_id: second_shopper.id, at: moment("2026-08-20 08:04:00"))

    refute_nil second
    assert_equal 2, record.events.where(event_type: "view").count
  end

  test "favorite, unfavorite and cart_add are recorded every time, with no collapse" do
    record = create_listing
    shopper = create_verified_customer

    first = record.record_event!("favorite", customer_id: shopper.id, at: moment("2026-08-20 08:03:00"))
    second = record.record_event!("favorite", customer_id: shopper.id, at: moment("2026-08-20 08:04:00"))

    refute_nil first
    refute_nil second
    assert_equal 2, record.events.where(event_type: "favorite").count
  end

  test "a search with no filters returns everything for sale" do
    for_sale = create_listing(status: :for_sale)
    create_listing(status: :draft)

    assert_equal [ for_sale.id ], Listing.search.pluck(:id)
  end

  test "a search drops a listing an admin removed" do
    for_sale = create_listing(status: :for_sale)
    removed = create_listing(status: :for_sale)
    removed.remove!(kind: :temporary, reason: "Reported.", by: create_admin)

    assert_equal [ for_sale.id ], Listing.search.pluck(:id)
  end

  test "a search matches the title, the description, and the medium" do
    titled = create_listing(title: "Harbour at Dusk", description: "Boats", medium: "Oil on canvas")
    described = create_listing(title: "Kiln Fired", description: "A dusk-lit vessel", medium: "Ceramic")
    in_medium = create_listing(title: "Winter Field", description: "Snow", medium: "Dusk pastel")
    create_listing(title: "Morning Light", description: "Sun", medium: "Watercolour")

    assert_equal [ titled, described, in_medium ].map(&:id).sort, Listing.search(term: "dusk").pluck(:id).sort
  end

  test "a search drops the wildcards a visitor typed" do
    create_listing(title: "Harbour at Dusk", description: "Boats")

    assert_empty Listing.search(term: "Harbour%Dusk")
  end

  test "a search narrows to one medium" do
    ceramic = create_listing(title: "Kiln Fired", medium: "Ceramic")
    create_listing(title: "Harbour at Dusk", medium: "Oil on canvas")

    assert_equal [ ceramic.id ], Listing.search(medium: "Ceramic").pluck(:id)
  end

  test "a term and a medium narrow together" do
    both = create_listing(title: "Harbour Ceramic", description: "Boats", medium: "Ceramic")
    create_listing(title: "Harbour at Dusk", description: "Boats", medium: "Oil on canvas")
    create_listing(title: "Winter Field", description: "Snow", medium: "Ceramic")

    assert_equal [ both.id ], Listing.search(term: "harbour", medium: "Ceramic").pluck(:id)
  end

  test "the media offered are the ones something is for sale in" do
    artist = create_seller
    create_listing(artist, medium: "Ceramic")
    create_listing(artist, medium: "Ceramic")
    create_listing(artist, medium: "Watercolour", status: :draft)
    create_listing(artist, medium: nil)

    assert_equal [ "Ceramic" ], Listing.media_for_sale
  end

  test "its totals add up its own events" do
    record = create_listing
    record.record_event!("view", at: moment("2026-08-20 08:00:00"))
    record.record_event!("view", at: moment("2026-08-20 09:00:00"))
    record.record_event!("favorite")
    record.record_event!("unfavorite")

    totals = record.activity_totals

    assert_equal 2, totals.views
    assert_equal 1, totals.favorites
    assert_equal 0, totals.cart_adds
  end

  test "the daily breakdown keeps a row per day, oldest first" do
    record = create_listing
    record.record_event!("view", at: moment("2026-08-21 09:00:00"))

    days = record.activity_by_day(days: 3, ends_on: moment("2026-08-22 17:30:00"))

    assert_equal [ Date.new(2026, 8, 20), Date.new(2026, 8, 21), Date.new(2026, 8, 22) ], days.map(&:date)
    assert_equal 0, days[0].totals.total
    assert_equal 1, days[1].totals.views
  end

  test "the daily breakdown ignores events outside the window" do
    record = create_listing
    record.record_event!("view", at: moment("2026-07-01 09:00:00"))

    days = record.activity_by_day(days: 2, ends_on: moment("2026-08-22 17:30:00"))

    assert_equal 0, days.sum { |day| day.totals.total }
  end

  test "the daily breakdown covers at least one day" do
    assert_raises(ArgumentError) { create_listing.activity_by_day(days: 0) }
  end

  test "an upload is attached on save" do
    record = draft
    record.image = uploaded_image("first.png")
    record.save!

    assert_predicate record.reload.image, :attached?
    assert_equal "first.png", record.image.filename.to_s
  end

  test "a new upload replaces the image" do
    record = created
    record.update!(image: uploaded_image("first.png"))

    record.update!(image: uploaded_image("second.png"))

    assert_equal "second.png", record.reload.image.filename.to_s
  end

  test "an edit with no upload keeps the image" do
    record = created
    record.update!(image: uploaded_image("first.png"))

    record.update!(title: "Harbour at Dawn", image: "")

    assert_equal "first.png", record.reload.image.filename.to_s
  end

  test "a listing without an upload renders a placeholder image" do
    record = create_listing(title: "Blue Heron")

    assert record.image_url.start_with?("data:image/svg+xml;base64,")
  end

  test "an uploaded image is served through Active Storage" do
    record = create_listing(title: "Blue Heron")
    record.image.attach(io: StringIO.new("<svg/>"), filename: "heron.svg", content_type: "image/svg+xml")

    assert_equal record.id, record.image_attachment.record_id
    assert_match %r{\A/rails/active_storage/blobs/}, record.image_url
  end

  test "a rejected edit still shows the image the listing has" do
    record = created
    record.update!(image: uploaded_image("first.png"))

    refute record.update(quantity: "1000", image: uploaded_image("second.png"))
    assert_match %r{\A/rails/active_storage/blobs/}, record.image_url
  end

  private

  def draft(**overrides)
    Listing.new({
      seller: create_seller,
      title: "Harbour at Dusk",
      description: "Oil on canvas.",
      medium: "Oil",
      dimensions: "40 x 60 cm",
      price: "249.00",
      quantity: "2"
    }.merge(overrides))
  end

  def created(**overrides)
    draft(**overrides).tap(&:save!)
  end

  def uploaded_image(filename)
    upload("image/png", "\x89PNG\r\n\x1a\n", filename)
  end

  # `ImageFormat` reads the type out of the bytes, so a refused upload carries
  # a real header rather than a claim in the request.
  def uploaded_pdf
    upload("application/pdf", "%PDF-1.4\n", "harbour.pdf")
  end

  def uploaded_svg
    upload("image/svg+xml", '<svg xmlns="http://www.w3.org/2000/svg"></svg>', "harbour.svg")
  end

  # A real PNG signature padded out to +byte_count+ bytes, so a cap test
  # exercises a file `ImageFormat` would otherwise accept.
  def image_of_size(byte_count)
    png = "\x89PNG\r\n\x1a\n"
    upload("image/png", png + ("\x00" * (byte_count - png.bytesize)), "harbour.png")
  end

  def upload(content_type, bytes, filename)
    Rack::Test::UploadedFile.new(StringIO.new(bytes), content_type, true, original_filename: filename)
  end
end
