class Listing < ApplicationRecord
  LINE_LIMIT = 255
  DESCRIPTION_LIMIT = 5_000
  QUANTITY_LIMIT = 999
  DOLLARS = /\A\d+(\.\d{1,2})?\z/
  SLUG_FALLBACK = "listing".freeze
  TEXT_MATCH = "title LIKE :pattern OR description LIKE :pattern OR medium LIKE :pattern".freeze

  belongs_to :seller
  has_many :events, class_name: "ListingEvent", dependent: :destroy
  has_many :favorites, dependent: :destroy
  has_many :cart_items, dependent: :destroy
  has_many :order_items, dependent: :restrict_with_error
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

  # A sold listing keeps its page so the links a buyer already followed still
  # lead somewhere; a draft or archived one was never public.
  ON_STOREFRONT = %w[for_sale sold].freeze
  scope :on_storefront, -> { where(status: ON_STOREFRONT) }

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
    listings = for_sale
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
    for_sale.where.not(medium: [nil, ""]).distinct.order(:medium).pluck(:medium)
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
  # image. The portal replaces an image, it never removes one.
  def image=(upload)
    super if upload.present?
  end

  def transition_to!(status)
    update!(status: self.class.transition(self.status, status))
  end

  def next_statuses
    TRANSITIONS.fetch(status, [])
  end

  def purchasable?
    for_sale? && quantity.positive?
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

  def record_event!(event_type, customer_id: nil, at: Time.current)
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

  # Only the upload being attached is worth checking; what is already stored
  # was checked when it arrived.
  def image_is_an_image
    upload = attachment_changes["image"]
    return if upload.nil? || upload.blob.content_type.to_s.start_with?("image/")

    errors.add(:image, "Upload an image file.")
  end
end
