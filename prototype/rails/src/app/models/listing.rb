class Listing < ApplicationRecord
  prefixed_id :lst

  LINE_LIMIT = 255
  DESCRIPTION_LIMIT = 5_000
  QUANTITY_LIMIT = 999
  DOLLARS = /\A\d+(\.\d{1,2})?\z/
  SLUG_FALLBACK = "listing".freeze
  TEXT_MATCH = "title LIKE :pattern OR description LIKE :pattern OR medium LIKE :pattern".freeze

  # The size limit an uploaded listing image is held to, stated on the
  # form's own help text below. config/boot.rb reads the same
  # UploadLimits::MAX_IMAGE_BYTES to size the transport-level Rack limit
  # that backs this check up, so the two cannot drift apart.
  MAX_IMAGE_UPLOAD_BYTES = UploadLimits::MAX_IMAGE_BYTES

  belongs_to :seller
  has_many :events, class_name: "ListingEvent", dependent: :destroy
  has_many :favorites, dependent: :destroy
  has_many :cart_items, dependent: :destroy
  has_many :order_items, dependent: :restrict_with_error
  has_many :faqs, class_name: "ListingFaq", dependent: :destroy
  has_many :conversations, as: :subject, dependent: :destroy
  has_many :removals, -> { order(:created_at, :id) }, class_name: "ListingRemoval", dependent: :destroy
  has_one_attached :image

  enum :status, { draft: "draft", for_sale: "for_sale", sold: "sold", archived: "archived" }, default: :draft

  TRANSITIONS = {
    "draft" => %w[for_sale archived].freeze,
    "for_sale" => %w[sold archived].freeze,
    # A declined card hands the stock back, so a sold-out listing returns to
    # the storefront.
    "sold" => %w[for_sale].freeze,
    "archived" => [].freeze
  }.freeze

  # An admin removal that stands refuses this move regardless of the caller.
  REMOVED_LISTING_MESSAGE = "This listing was removed by an admin and cannot be put back on sale.".freeze

  # A sold listing keeps its page so the links a buyer already followed still
  # lead somewhere; a draft or archived one was never public. An active
  # removal takes a listing off whatever its status says.
  ON_STOREFRONT = %w[for_sale sold].freeze
  scope :on_storefront, -> { where(status: ON_STOREFRONT).visible }

  # What the admin directory narrows the table to. `any` is the filter a page
  # carries when nobody has chosen one.
  REMOVAL_STANDINGS = %w[any removed visible].freeze

  scope :with_status, ->(status) { where(status: status) if status.present? }
  scope :for_seller, ->(seller_id) { where(seller_id: seller_id) if seller_id.present? }
  # An admin's removal takes a listing off the storefront whatever its status.
  scope :removed, -> { where(id: ListingRemoval.active.select(:listing_id)) }
  scope :visible, -> { where.not(id: ListingRemoval.active.select(:listing_id)) }
  scope :removal_standing, ->(standing) {
    case standing
    when "removed" then removed
    when "visible" then visible
    end
  }

  normalizes :title, :description, :medium, :dimensions, with: ->(text) { text.strip.presence }

  validates :title,
    presence: { message: "Enter a title." },
    length: { maximum: LINE_LIMIT, message: "Keep the title under #{LINE_LIMIT} characters." }
  validates :description,
    length: { maximum: DESCRIPTION_LIMIT, message: "Keep the description under #{DESCRIPTION_LIMIT} characters." }
  validates :medium, length: { maximum: LINE_LIMIT, message: "Keep the medium under #{LINE_LIMIT} characters." }
  validates :dimensions,
    length: { maximum: LINE_LIMIT, message: "Keep the dimensions under #{LINE_LIMIT} characters." }
  validates :price, format: { with: DOLLARS, message: "The price is an amount in dollars, like 249.00." }
  validates :quantity,
    numericality: {
      only_integer: true, in: 0..QUANTITY_LIMIT,
      message: "The quantity is a whole number from 0 to #{QUANTITY_LIMIT}."
    }
  validate :image_is_an_image

  before_validation :assign_slug, on: :create

  # The storefront URL a listing is shared under. Titles repeat between
  # sellers, so a slug that is already taken counts up until it is free.
  def self.first_free_slug(title)
    base = title.to_s.parameterize.presence || SLUG_FALLBACK
    taken = where("slug LIKE ?", "#{base}%").pluck(:slug)
    return base unless taken.include?(base)

    suffix = 2
    suffix += 1 while taken.include?("#{base}-#{suffix}")

    "#{base}-#{suffix}"
  end

  # Putting a listing up for sale is the move the log names on its own; the
  # rest of the lifecycle reads as one event carrying where it went.
  def self.transition_event(to)
    to.to_s == "for_sale" ? "listing.publish" : "listing.transition"
  end

  # The lifecycle move as a value, for a caller that works out a status without
  # a record to write it to.
  def self.transition(from, to)
    raise TransitionError, "A listing cannot move from #{from} to #{to}." unless
      TRANSITIONS.fetch(from, []).include?(to)

    to
  end

  # What a storefront visitor asked to see: free text over the catalogue and a
  # medium to narrow it to. Either half may be missing.
  def self.search(term: nil, medium: nil)
    listings = for_sale.visible
    listings = listings.where(TEXT_MATCH, pattern: like_pattern(term)) if term.present?
    listings = listings.where(medium: medium) if medium.present?

    listings
  end

  # SQLite LIKE has no escape character unless the query names one, so a
  # wildcard the visitor typed is dropped rather than escaped.
  def self.like_pattern(term)
    "%#{term.tr('%_', '  ').squeeze(' ').strip}%"
  end

  # The media the storefront filter offers, which are the ones something is
  # currently on sale in.
  def self.media_for_sale
    for_sale.visible.where.not(medium: [ nil, "" ]).distinct.order(:medium).pluck(:medium)
  end

  # The form edits dollars; the column stores cents. What the seller typed is
  # kept as it was typed, so a rejected form renders their own text back.
  def price
    @price || (format("%d.%02d", price_cents / 100, price_cents % 100) if price_cents)
  end

  def price=(dollars)
    @price = dollars.to_s.strip
    self.price_cents = cents_in(@price)
  end

  # A file field left empty posts as "", which would otherwise detach the
  # image. The portal replaces an image, it never removes one. A file upload
  # is judged before Active Storage ever sees it: over the size cap, or bytes
  # `ImageFormat` does not recognise (SVG included — stored script, not a
  # format with a signature), never reaches `attach`.
  def image=(upload)
    return if upload.blank?
    return super unless upload.respond_to?(:read) && upload.respond_to?(:size)

    @image_upload_rejection = image_upload_rejection(upload)
    super if @image_upload_rejection.nil?
  end

  # A removal blocks a return to `for_sale` regardless of caller, so a stale
  # form or a direct POST cannot undo one the seller's own buttons already hide.
  def transition_to!(status)
    from = self.status

    Story.tell(self.class.transition_event(status), "moving the listing from #{from} to #{status}",
      listing_id: id, status_from: from, status_to: status.to_s) do |story|
      raise TransitionError, REMOVED_LISTING_MESSAGE if status.to_s == "for_sale" && actively_removed?

      moved = update!(status: self.class.transition(from, status))

      story.did("moved the listing to #{status}",
        listing_id: id, status_from: from, status_to: status.to_s)

      moved
    end
  end

  # The moves the lifecycle allows, with `for_sale` dropped while an admin
  # removal stands — what feeds both the seller's status buttons and the
  # refusal `transition_to!` raises if one is tried anyway.
  def next_statuses
    moves = TRANSITIONS.fetch(status, [])
    return moves unless actively_removed?

    moves - %w[for_sale]
  end

  def purchasable?
    for_sale? && quantity.positive? && !actively_removed?
  end

  # The removal standing over this listing right now, if any. Both
  # `remove!` and `lift_removal!` key off this rather than a removal id, so a
  # page that knows the listing needs nothing else. Reads over `removals`
  # rather than the `active` scope, so a caller that preloaded the
  # association (a directory row) pays no query per listing.
  def active_removal
    removals.detect(&:active?)
  end

  # Whether an admin has pulled this listing off the storefront independent of
  # its status.
  def actively_removed?
    active_removal.present?
  end

  # Takes the listing off the storefront whatever its status. At most one
  # removal is active at a time: raising a temporary removal to a permanent
  # one is lift then remove, which leaves the seller one reason to read
  # rather than two overlapping ones.
  def remove!(kind:, reason:, by:)
    Story.tell("moderation.remove_listing", "removing the listing from the storefront",
      listing_id: id, kind: kind.to_s) do |story|
      raise TransitionError, "listing #{id} is already removed" if actively_removed?

      removal = removals.create!(kind: kind, reason: reason, admin: by)

      story.did("removed the listing from the storefront",
        listing_id: id, removal_id: removal.id, kind: kind.to_s)

      removal
    end
  end

  # Puts a temporarily removed listing back under its own status. A permanent
  # removal is refused here rather than hidden in the page that offers it, so
  # a stale form cannot undo one.
  def lift_removal!
    Story.tell("moderation.lift_listing_removal", "lifting the removal", listing_id: id) do |story|
      removal = active_removal
      raise TransitionError, "listing #{id} is not removed" if removal.nil?
      raise TransitionError, "a permanent removal cannot be lifted" unless removal.liftable?

      removal.update!(lifted_at: Time.current)

      story.did("lifted the removal", listing_id: id, removal_id: removal.id)

      removal
    end
  end

  # Whether a shopper can reach this listing's page: a status that keeps it
  # public and no removal standing over it.
  def on_storefront?
    ON_STOREFRONT.include?(status) && !actively_removed?
  end

  # An order claims stock when it is placed and hands it back when the card is
  # declined, which puts a listing that had sold out back on the storefront.
  def take_stock!(count)
    reject_an_empty_change(count)
    raise ArgumentError, "a listing that is #{status} cannot be sold" unless for_sale?
    raise ArgumentError, "a listing with #{quantity} left cannot sell #{count}" if count > quantity

    remaining = quantity - count
    update!(quantity: remaining, status: remaining.zero? ? self.class.transition(status, "sold") : status)
  end

  def restore_stock!(count)
    reject_an_empty_change(count)

    update!(quantity: quantity + count, status: sold? ? self.class.transition(status, "for_sale") : status)
  end

  # A view collapses to at most one row per (listing, customer, UTC hour); an
  # anonymous customer's row still counts as a customer, so two anonymous
  # visitors in the same hour each leave their own view. The collapse
  # returns nil rather than a row; the caller is the unit of work with a
  # Story to answer, so it decides how the collapse reads in the log.
  def record_event!(event_type, customer_id: nil, at: Time.current)
    return nil if ListingEvent.recorded_once_per_hour?(event_type) && collapsed_view?(customer_id, at)

    events.create!(event_type: event_type, customer_id: customer_id, occurred_at: at)
  end

  def activity_totals
    ListingEvent::Totals.from(events.group(:event_type).count)
  end

  # A gapless run of days ending on the day of +ends_on+, oldest first, so the
  # breakdown keeps a row for every day a seller looks at.
  def activity_by_day(days:, ends_on: Time.current)
    raise ArgumentError, "a timeline covers at least one day, got #{days}" if days < 1

    counts = event_counts_by_date
    last_day = ends_on.to_date

    ((last_day - (days - 1))..last_day).map do |day|
      ListingEvent::Day.new(date: day, totals: ListingEvent::Totals.from(counts.fetch(day, {})))
    end
  end

  # A pending upload has no URL until it is saved, so a form rendered back with
  # a rejected edit still shows the image the listing has.
  def image_url
    return PlaceholderImage.data_uri(title) if image_attachment.nil?

    Rails.application.routes.url_helpers.rails_blob_path(image_attachment.blob, only_path: true)
  end

  private

  def collapsed_view?(customer_id, at)
    events.where(event_type: "view", customer_id: customer_id)
          .where(occurred_at: ListingEvent.view_window_start(at)..)
          .exists?
  end

  def event_counts_by_date
    events.pluck(:occurred_at, :event_type)
          .group_by { |occurred_at, _| occurred_at.to_date }
          .transform_values { |events| events.map(&:last).tally }
  end

  def reject_an_empty_change(count)
    raise ArgumentError, "a stock change covers at least one item, got #{count}" if count < 1
  end

  def cents_in(dollars)
    return unless DOLLARS.match?(dollars)

    whole, fraction = dollars.split(".")

    whole.to_i * 100 + fraction.to_s.ljust(2, "0").to_i
  end

  def assign_slug
    self.slug = self.class.first_free_slug(title) if slug.blank?
  end

  # Only the upload just handed to `image=` is worth checking; what is
  # already stored was checked when it arrived.
  def image_is_an_image
    errors.add(:image, @image_upload_rejection) if @image_upload_rejection
  end

  # nil accepts the upload; a message names why it does not reach `attach`.
  def image_upload_rejection(upload)
    return "Upload an image under #{MAX_IMAGE_UPLOAD_BYTES / 1.megabyte} MB." if upload.size > MAX_IMAGE_UPLOAD_BYTES

    bytes = upload.read(ImageFormat::SNIFF_BYTES)
    upload.rewind

    "Upload an image file." if ImageFormat.sniff(bytes).nil?
  end
end
