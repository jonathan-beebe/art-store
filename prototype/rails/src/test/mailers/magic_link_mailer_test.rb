require "test_helper"

class MagicLinkMailerTest < ActionMailer::TestCase
  test "it goes to the address the link was issued for" do
    assert_equal [ "artist@example.com" ], sign_in_mail.to
    assert_equal [ "noreply@artstore.test" ], sign_in_mail.from
    assert_equal "Your sign-in link", sign_in_mail.subject
  end

  test "both parts carry the URL that verifies the token" do
    assert_includes sign_in_mail.text_part.body.decoded, URL
    assert_includes sign_in_mail.html_part.body.decoded, URL
  end

  test "both parts say how long the link lasts" do
    assert_includes sign_in_mail.text_part.body.decoded, "expires 15 minutes"
    assert_includes sign_in_mail.html_part.body.decoded, "expires 15 minutes"
  end

  private

  URL = "http://example.com/auth/magic/abc123"

  def sign_in_mail
    @sign_in_mail ||= begin
      link, = MagicLink.issue(email: "artist@example.com", actor_type: :seller)

      MagicLinkMailer.with(link: link, url: URL).sign_in
    end
  end
end
