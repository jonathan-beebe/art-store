require "test_helper"

class MagicLinkTest < ActiveSupport::TestCase
  test "issue writes a link for the address" do
    link, = MagicLink.issue(email: "artist@example.com", actor_type: :seller)

    assert_predicate link, :persisted?
    assert_equal "artist@example.com", MagicLink.sole.email
  end

  test "issue normalizes the address before storing it" do
    MagicLink.issue(email: "  Artist@Example.COM ", actor_type: :seller)

    assert_equal "artist@example.com", MagicLink.sole.email
  end

  test "issue records which side of the marketplace asked" do
    MagicLink.issue(email: "buyer@example.com", actor_type: :customer)

    assert_equal "customer", MagicLink.sole.actor_type
    assert_predicate MagicLink.sole, :customer?
  end

  test "issue records a link asked for from the admin site" do
    MagicLink.issue(email: "ops@example.com", actor_type: :admin)

    assert_predicate MagicLink.sole, :admin?
  end

  test "issue carries the destination the visitor was headed for" do
    MagicLink.issue(email: "buyer@example.com", actor_type: :customer, redirect_to: "/orders/7/pay")

    assert_equal "/orders/7/pay", MagicLink.sole.redirect_to
  end

  test "issue expires the link after the configured window" do
    freeze_time do
      MagicLink.issue(email: "artist@example.com", actor_type: :seller)

      assert_equal Rails.configuration.x.magic_links.expiry_minutes.minutes.from_now, MagicLink.sole.expires_at
    end
  end

  test "issue expires the link from the moment it is asked for" do
    MagicLink.issue(email: "artist@example.com", actor_type: :seller, now: 1.hour.ago)

    assert_predicate MagicLink.sole, :expired?
  end

  test "issue leaves the link unconsumed" do
    MagicLink.issue(email: "artist@example.com", actor_type: :seller)

    refute_predicate MagicLink.sole, :consumed?
  end

  test "issue stores only the digest of the token it hands out" do
    link, token = MagicLink.issue(email: "artist@example.com", actor_type: :seller)

    refute_equal token, link.token_digest
    assert_equal Digest::SHA256.hexdigest(token), link.token_digest
  end

  test "two links for the same address carry different tokens" do
    MagicLink.issue(email: "artist@example.com", actor_type: :seller)
    MagicLink.issue(email: "artist@example.com", actor_type: :seller)

    assert_equal 2, MagicLink.distinct.count(:token_digest)
  end

  test "issue writes no link for an address without an at sign" do
    link, = MagicLink.issue(email: "artist.example.com", actor_type: :seller)

    refute_predicate link, :persisted?
    assert_equal 0, MagicLink.count
  end

  test "issue writes no link for an address without a dotted domain" do
    link, = MagicLink.issue(email: "artist@example", actor_type: :seller)

    refute_predicate link, :persisted?
  end

  test "issue writes no link for an address carrying whitespace" do
    link, = MagicLink.issue(email: "artist name@example.com", actor_type: :seller)

    refute_predicate link, :persisted?
  end

  test "issue writes no link for a blank address" do
    link, = MagicLink.issue(email: "   ", actor_type: :seller)

    refute_predicate link, :persisted?
  end

  test "issue writes no link when no address is given" do
    link, = MagicLink.issue(email: nil, actor_type: :seller)

    refute_predicate link, :persisted?
  end

  test "find_by_token finds the link holding that token's digest" do
    token, link = create_magic_link

    assert_equal link, MagicLink.find_by_token(token)
  end

  test "find_by_token finds nothing for a token no link was issued for" do
    create_magic_link

    assert_nil MagicLink.find_by_token("not-a-token")
  end

  test "a fresh unconsumed link is usable" do
    _token, link = create_magic_link(expires_at: 15.minutes.from_now)

    assert_predicate link, :usable?
  end

  test "a link expires the moment now reaches the expiry" do
    freeze_time do
      _token, link = create_magic_link(expires_at: Time.current)

      assert_predicate link, :expired?
      refute_predicate link, :usable?
    end
  end

  test "a link past its expiry is expired" do
    _token, link = create_magic_link(expires_at: 1.minute.ago)

    assert_predicate link, :expired?
  end

  test "consume marks the link used so it cannot sign anyone in again" do
    _token, link = create_magic_link

    assert link.consume

    assert_predicate link, :consumed?
    refute_predicate link, :usable?
  end

  test "a consumed link stays consumed once it also passes its expiry" do
    _token, link = create_magic_link(expires_at: 1.minute.ago)

    link.consume

    assert_predicate link, :consumed?
  end

  test "consume updates the row so a second load also reads it as spent" do
    _token, link = create_magic_link

    link.consume

    assert_predicate MagicLink.find(link.id), :consumed?
  end

  # Two requests racing to verify the same link each load their own copy of
  # the row before either writes — that is the shape of the race this guards
  # against, not two threads sharing one Ruby object. `consume` on the first
  # copy commits the row as spent; `consume` on the second still sees a
  # freshly loaded, unconsumed `consumed_at` in memory, but its own `UPDATE
  # ... WHERE consumed_at IS NULL` matches nothing once it runs, because the
  # first copy's write already landed. Only one of the two returns true.
  test "consume refuses a second copy of the link loaded before the first was spent" do
    _token, link = create_magic_link
    racer_a = MagicLink.find(link.id)
    racer_b = MagicLink.find(link.id)

    first = racer_a.consume
    second = racer_b.consume

    assert first
    refute second
    assert_predicate MagicLink.find(link.id), :consumed?
  end

  test "consume is a single conditional UPDATE, not a read followed by a write" do
    _token, link = create_magic_link
    statements = []
    subscriber = ->(_name, _started, _finished, _id, payload) {
      statements << payload[:sql] unless %w[SCHEMA TRANSACTION].include?(payload[:name])
    }

    ActiveSupport::Notifications.subscribed(subscriber, "sql.active_record") { link.consume }

    assert_equal 1, statements.size
    assert_match(/\AUPDATE/i, statements.first)
    assert_match(/consumed_at.*IS NULL/mi, statements.first)
  end
end
