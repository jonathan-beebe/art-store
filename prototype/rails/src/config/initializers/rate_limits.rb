# The seven rate limits `docs/alignment.md` §3 fixes, parsed from their
# environment variables into a frozen table the `RateLimiting` concern reads
# by name. A value is "<count>/<window>" (window "<n>s", "<n>m", or "<n>h"),
# "off" to disable the limit, or unset to take the default below. A value
# that matches none of those shapes refuses to boot, naming the variable and
# the value that would not parse — the same way `STALE_ORDER_HOURS` does in
# `config/initializers/orders.rb`, just with a message specific enough to
# name what was wrong.
module RateLimits
  Limit = Data.define(:name, :count, :window_seconds, :enabled) do
    def enabled?
      enabled
    end
  end

  ENV_VARS = {
    magic_link_request: "RATE_LIMIT_MAGIC_LINK_REQUEST",
    magic_link_consume: "RATE_LIMIT_MAGIC_LINK_CONSUME",
    message_post: "RATE_LIMIT_MESSAGE_POST",
    conversation_open: "RATE_LIMIT_CONVERSATION_OPEN",
    checkout: "RATE_LIMIT_CHECKOUT",
    payment_attempt: "RATE_LIMIT_PAYMENT_ATTEMPT",
    listing_write: "RATE_LIMIT_LISTING_WRITE"
  }.freeze

  DEFAULTS = {
    magic_link_request: "5/15m",
    magic_link_consume: "20/15m",
    message_post: "30/1h",
    conversation_open: "10/1h",
    checkout: "10/1h",
    payment_attempt: "5/15m",
    listing_write: "60/1h"
  }.freeze

  # A count and a window, each at least 1, so "0/15m" and "-1/15m" fall
  # through to the boot refusal below with everything else that is not this
  # shape.
  VALUE = /\A([1-9]\d*)\/([1-9]\d*)(s|m|h)\z/
  SECONDS_PER_UNIT = { "s" => 1, "m" => 60, "h" => 3_600 }.freeze

  MalformedValue = Class.new(StandardError)

  # The limit a controller names itself. A name the table does not hold is a
  # programming error, not a configuration one, so it raises the same way
  # `Hash#fetch` always has.
  def self.fetch(name)
    CONFIG.fetch(name)
  end

  def self.parse(name, env_var, raw)
    return Limit.new(name: name.to_s, count: nil, window_seconds: nil, enabled: false) if raw == "off"

    match = VALUE.match(raw)
    unless match
      raise MalformedValue,
        "#{env_var}=#{raw.inspect} is not \"<count>/<window>\" (for example \"5/15m\") or \"off\""
    end

    count = match[1].to_i
    window_seconds = match[2].to_i * SECONDS_PER_UNIT.fetch(match[3])
    Limit.new(name: name.to_s, count: count, window_seconds: window_seconds, enabled: true)
  end

  CONFIG = ENV_VARS.to_h { |name, env_var|
    [ name, parse(name, env_var, ENV.fetch(env_var, DEFAULTS.fetch(name))) ]
  }.freeze

  # A store of its own rather than `Rails.cache`: development runs on
  # `:memory_store` and test on `:null_store` (see the environment configs),
  # neither of which survives a restart or holds anything at all, but a
  # limit's counters must — in every environment, including the ones a test
  # travels through with `travel_to`. Solid Cache keeps its
  # `solid_cache_entries` table in this app's own SQLite database, the same
  # single-database way `solid_cable_messages` already does for Action
  # Cable: no `config/cache.yml`, no `connects_to`, so the gem falls back to
  # the default Active Record connection.
  STORE = SolidCache::Store.new
end

# Client ip comes from the socket unless the deployment sits behind a proxy
# that sets it. `TRUSTED_PROXIES` names that proxy (a comma-separated list of
# addresses or CIDR ranges); Rails' own trusted-proxies list is otherwise
# left alone, so nothing but an operator's explicit setting makes the app
# trust a forwarded-for header.
if ENV["TRUSTED_PROXIES"].present?
  Rails.application.config.action_dispatch.trusted_proxies =
    ENV["TRUSTED_PROXIES"].split(",").map { |address| IPAddr.new(address.strip) }
end
