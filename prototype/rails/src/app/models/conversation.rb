class Conversation < ApplicationRecord
  # Where an actor of one type sits in a conversation, which site they read one
  # on, and where they read it.
  Side = Data.define(:column, :site, :inbox_path)

  SIDES = {
    seller: Side.new(column: :seller_id, site: "seller", inbox_path: "/seller/messages"),
    customer: Side.new(column: :customer_id, site: "shop", inbox_path: "/messages"),
    admin: Side.new(column: :admin_id, site: "admin", inbox_path: "/admin/messages")
  }.freeze

  # The two sides a kind of conversation has, what it is about, and how it
  # reads in an inbox row and a notification.
  Kind = Data.define(:sides, :subject_type, :topic)

  SUPPORT_DESK = "Art Store support".freeze

  # What makes a thread the one thread: its kind, the participants it names,
  # and what it is about. The unique index over these columns reads the same
  # list.
  SHAPE = %w[kind seller_id customer_id admin_id subject_type subject_id].freeze

  # One table serves every pairing, so the kind is the one source for which
  # participants a thread names and which subject, if any, it hangs off.
  KINDS = {
    "admin_seller" => Kind.new(sides: %i[admin seller], subject_type: nil, topic: ->(_) { SUPPORT_DESK }),
    "admin_customer" => Kind.new(sides: %i[admin customer], subject_type: nil, topic: ->(_) { SUPPORT_DESK }),
    "fulfillment" => Kind.new(
      sides: %i[seller customer], subject_type: "Fulfillment",
      topic: ->(fulfillment) { "order ##{fulfillment.order_id}" }
    ),
    "listing_question" => Kind.new(
      sides: %i[seller customer], subject_type: "Listing",
      topic: ->(listing) { "“#{listing.title}”" }
    )
  }.freeze

  belongs_to :seller, optional: true
  belongs_to :customer, optional: true
  belongs_to :admin, optional: true
  belongs_to :subject, polymorphic: true, optional: true
  has_many :messages, dependent: :destroy

  enum :kind, KINDS.keys.to_h { |kind| [ kind.to_sym, kind ] }, validate: true

  validate :sides_match_the_kind
  validate :subject_matches_the_kind

  scope :involving, ->(actor) { where(side_of(actor).column => actor.id).order(last_message_at: :desc) }

  # The one thread on a subject, opened if it is not there yet. Every entry
  # point — the admin's seller page, a question on a listing, an order's
  # "message the seller" — lands on the same conversation, so a reply reaches
  # the place the last one came from.
  def self.open(kind:, subject: nil, at: Time.current, **participants)
    named = KINDS.fetch(kind.to_s).sides.index_with { |side| participants.fetch(side) }
    shape = { kind: kind, subject: subject, **named }

    where(shape).order(:id).first || create!(**shape, last_message_at: at)
  end

  # Which side of a conversation this actor sits on, whatever kind it is.
  def self.side_of(actor)
    SIDES.fetch(actor.model_name.singular.to_sym)
  end

  # Which of the three sites this actor reads their threads on. A broadcast
  # picks the partial from here, so a seller is sent the portal's markup and a
  # shopper the storefront's.
  def self.site_of(actor)
    side_of(actor).site
  end

  # The two actors the thread is between, in the order the kind names them.
  def participants
    KINDS.fetch(kind).sides.map { |side| public_send(side) }
  end

  def participant?(actor)
    participants.include?(actor)
  end

  # Who the actor is talking to. Every kind has exactly two sides, so the
  # counterpart is the participant the actor is not.
  def counterpart_of(actor)
    participants.find { |participant| participant != actor }
  end

  # What the thread is about, in the words an inbox row and a "New message"
  # notification both use.
  def topic
    KINDS.fetch(kind).topic.call(subject)
  end

  # Where this actor reads this thread, on their own site. The three sites
  # carry the same conversation under three paths, and a notification points
  # its recipient at theirs.
  def thread_path_for(actor)
    "#{self.class.side_of(actor).inbox_path}/#{id}"
  end

  # The element a thread page hangs its messages on, and the target an arriving
  # message is appended to.
  def messages_dom_id
    ActionView::RecordIdentifier.dom_id(self, :messages)
  end

  # Hands the thread to another customer, which is what a merge of two customer
  # identities asks for. A customer already holding a thread of the same shape
  # takes this one's messages onto theirs and this row goes, so one thread per
  # kind, participants and subject survives the merge.
  def move_to(customer)
    standing = self.class.where(shape.merge("customer_id" => customer.id)).order(:id).first
    return update!(customer: customer) if standing.nil?

    transaction do
      messages.update_all(conversation_id: standing.id)
      standing.update!(last_message_at: [ standing.last_message_at, last_message_at ].max)
      destroy!
    end
  end

  # Appends one message, moves the thread to the top of both inboxes, and tells
  # the other side.
  def post!(sender, body, at: Time.current)
    raise ArgumentError, "a conversation takes messages from its participants only" unless participant?(sender)

    transaction do
      message = messages.create!(sender: sender, body: body)
      update!(last_message_at: at)
      recipient = counterpart_of(sender)
      Notification.new_message(message, url: thread_path_for(recipient))

      message
    end
  end

  # The last thing one side of the thread said.
  def latest_message_from(actor)
    messages.where(sender: actor).order(created_at: :desc, id: :desc).first
  end

  # How many messages in each of these threads the actor has not read, by
  # conversation id. One query serves a whole inbox. A thread with nothing
  # unread on it is absent from the group, so the hash answers 0 for it.
  def self.unread_counts_for(actor, conversations)
    counts = Message.unread_for(actor).where(conversation: conversations).group(:conversation_id).count
    counts.default = 0

    counts
  end

  def unread_count_for(actor)
    self.class.unread_counts_for(actor, [ self ])[id]
  end

  # Opening a thread reads what the other side sent. Returns how many messages
  # that was. The reader's badge follows the commit, so a reading that rolls
  # back leaves the badge where it was.
  def read_by!(reader, at: Time.current)
    read = messages.unread_for(reader).update_all(read_at: at)
    ActiveRecord.after_all_transactions_commit { reader.broadcast_unread_message_count } if read.positive?

    read
  end

  private

  def shape
    attributes.slice(*SHAPE)
  end

  def sides_match_the_kind
    shape = KINDS[kind]
    return if shape.nil?

    SIDES.each do |side, definition|
      named = public_send(definition.column).present?
      next if named == shape.sides.include?(side)

      errors.add(side, named ? "has no part in a #{kind} conversation." : "is missing.")
    end
  end

  def subject_matches_the_kind
    shape = KINDS[kind]
    return if shape.nil? || subject_type == shape.subject_type

    errors.add(:subject, subject_refusal(shape))
  end

  def subject_refusal(shape)
    return "has no part in a #{kind} conversation." if shape.subject_type.nil?

    "of a #{kind} conversation is a #{shape.subject_type}."
  end
end
