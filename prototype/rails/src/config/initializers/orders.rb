Rails.application.configure do
  # A guest's order holds its stock off the storefront until the address is
  # verified. This is how long that may last before the sweep calls it off.
  config.x.orders.stale_hours = Integer(ENV.fetch("STALE_ORDER_HOURS", "24"))
end
