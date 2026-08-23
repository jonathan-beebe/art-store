Rails.application.configure do
  # The prototype has no mailbox anyone can read, so outside production the
  # sign-in URL is also flashed into the debug alert both layouts render.
  config.x.magic_links.debug_alert = ActiveModel::Type::Boolean.new.cast(
    ENV.fetch("MAGIC_LINK_DEBUG_ALERT") { !Rails.env.production? }
  )
  config.x.magic_links.expiry_minutes = Integer(ENV.fetch("MAGIC_LINK_EXPIRY_MINUTES", "15"))
end
