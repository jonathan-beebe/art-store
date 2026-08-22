# How a sign-in link reaches the person who asked for it. An implementation
# answers `deliver(email:, url:)`.
module MagicLinkDelivery
  def self.build(flash)
    case Rails.configuration.x.magic_links.delivery.to_s
    when "flash" then FlashMagicLinkDelivery.new(flash)
    when "mail" then MailMagicLinkDelivery.new
    else raise ArgumentError, "unknown magic link delivery: #{Rails.configuration.x.magic_links.delivery.inspect}"
    end
  end
end
