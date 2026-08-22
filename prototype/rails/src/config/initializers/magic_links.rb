Rails.application.configure do
  # `flash` prints the link in the debug alert; `mail` is the hook for real
  # email and refuses to send until one exists.
  config.x.magic_links.delivery = ENV.fetch("MAGIC_LINK_DELIVERY", "flash")
  config.x.magic_links.expiry_minutes = Integer(ENV.fetch("MAGIC_LINK_EXPIRY_MINUTES", "15"))
end
