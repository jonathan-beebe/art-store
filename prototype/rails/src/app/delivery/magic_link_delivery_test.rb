require "test_helper"

class MagicLinkDeliveryTest < ActiveSupport::TestCase
  test "the prototype default flashes the link" do
    assert_instance_of FlashMagicLinkDelivery, build_with("flash")
  end

  test "mail is selected by name" do
    assert_instance_of MailMagicLinkDelivery, build_with("mail")
  end

  test "an unknown setting is refused rather than silently dropping links" do
    assert_raises(ArgumentError) { build_with("carrier_pigeon") }
  end

  private

  def build_with(setting)
    original = Rails.configuration.x.magic_links.delivery
    Rails.configuration.x.magic_links.delivery = setting

    MagicLinkDelivery.build(ActionDispatch::Flash::FlashHash.new)
  ensure
    Rails.configuration.x.magic_links.delivery = original
  end
end
