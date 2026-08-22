class FlashMagicLinkDelivery
  def initialize(flash)
    @flash = flash
  end

  # The debug-alert partial in both layouts renders whatever lands under this
  # key, which is how the prototype hands over a link with no mailbox.
  def deliver(email:, url:)
    @flash[:debug_magic_link] = url
  end
end
