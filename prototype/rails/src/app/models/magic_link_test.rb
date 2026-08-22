require "identity_test_case"

class MagicLinkTest < IdentityTestCase
  test "for_token finds the link holding that token's digest" do
    token, link = create_magic_link

    assert_equal link, MagicLink.for_token(token).first
  end

  test "for_token finds nothing for a token no link was issued for" do
    create_magic_link

    assert_nil MagicLink.for_token("not-a-token").first
  end

  test "the plaintext token is never stored" do
    token, link = create_magic_link

    refute_equal token, link.token_digest
  end

  test "actor_type reads back as the domain actor" do
    _token, link = create_magic_link(actor_type: Domain::Auth::ActorType::CUSTOMER)

    assert_equal Domain::Auth::ActorType::CUSTOMER, link.reload.actor_type
  end

  test "the address is normalized on the way in" do
    _token, link = create_magic_link(email: "  Artist@Example.COM ")

    assert_equal "artist@example.com", link.email
  end

  test "status_at reports a fresh link as usable" do
    _token, link = create_magic_link(expires_at: 15.minutes.from_now)

    assert_equal Domain::Auth::MagicLinkStatus::USABLE, link.status_at(Time.current)
  end

  test "status_at reports a link past its expiry as expired" do
    _token, link = create_magic_link(expires_at: 1.minute.ago)

    assert_equal Domain::Auth::MagicLinkStatus::EXPIRED, link.status_at(Time.current)
  end

  test "consume! marks the link used so it cannot sign anyone in again" do
    now = Time.current
    _token, link = create_magic_link

    link.consume!(now)

    assert_equal Domain::Auth::MagicLinkStatus::CONSUMED, link.status_at(now)
  end
end
