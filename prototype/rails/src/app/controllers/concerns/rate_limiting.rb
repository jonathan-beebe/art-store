# The seven limits `docs/alignment.md` §3 names, over the counters
# `RateLimits::STORE` keeps and the config `RateLimits::CONFIG` parses at
# boot. `rate_limit_guard` declares a limit that gates a whole action, over
# Rails' own `rate_limit` controller macro; `rate_limit_trip!` checks one
# imperatively, for `magic_link_request`, which keys on two facts at once and
# guards one shared method (`MagicLinkSender#send_magic_link`) rather than
# one action outright.
#
# Neither raises. A trip renders the 429 right where it is found — inside the
# macro's `with:`, or inside `rate_limit_trip!` itself — the same way a
# `before_action` halts the chain by rendering rather than by raising.
# `send_magic_link` reads a trip off `rate_limit_trip!`'s return value and
# returns early instead, which is what lets a call several frames from the
# action stop the same way a `before_action` would without an exception
# crossing frames a `before_action` never does — and without one landing on
# `RequestStory`'s own `rescue StandardError`, which would otherwise log the
# request's `did` line with a guessed status instead of the 429 actually
# answered.
module RateLimiting
  extend ActiveSupport::Concern

  # What a trip carries through to the response and the log line: the limit's
  # name, the key that tripped it, and how long until the window it tripped
  # in ends.
  Trip = Data.define(:limit, :key, :retry_after_seconds)

  class_methods do
    # Declares a limit named in `docs/alignment.md` §3 as a before_action, the
    # same shape as Rails' own `rate_limit to:, within:, by:, with:` macro
    # takes, so `only:`/`except:` work exactly as they would there. `by:` is
    # evaluated in the request, in the controller's own context, once to
    # build the counter's key and again, only on a trip, to render and log
    # with it.
    #
    # The window's start is folded into the cache key `by:` returns, so the
    # counter Rails' macro increments is a fresh one every window rather than
    # a count whose expiry `store.increment` keeps pushing back — a fixed
    # window per (name, key, window_start), the shape `docs/alignment.md` §3
    # asks for, out of a macro that does not know about windows on its own.
    def rate_limit_guard(name, by:, **options)
      config = RateLimits.fetch(name)
      return unless config.enabled?

      rate_limit(
        to: config.count, within: config.window_seconds,
        by: -> { RateLimiting.windowed_key(instance_exec(&by).to_s, config.window_seconds) },
        with: -> { render_rate_limit_trip(RateLimiting.trip(name, instance_exec(&by).to_s, config.window_seconds)) },
        store: RateLimits::STORE, scope: name.to_s, **options
      )
    end
  end

  # A window's worth of one key's count, folded into the string Rails'
  # `rate_limit` macro increments. The key changes every `window_seconds`, so
  # the count it addresses starts back at zero without any of the store's own
  # expiry semantics deciding when — those only clean the abandoned window up.
  def self.windowed_key(key, window_seconds)
    window_start = (Time.now.to_i / window_seconds) * window_seconds

    "#{key}:#{window_start}"
  end

  # How long until the window a key is in right now ends.
  def self.retry_after(window_seconds)
    window_seconds - (Time.now.to_i % window_seconds)
  end

  def self.trip(name, key, window_seconds)
    Trip.new(limit: name.to_s, key: key, retry_after_seconds: retry_after(window_seconds))
  end

  # `magic_link_request` guards one shared method (`MagicLinkSender#
  # send_magic_link`) called from four different actions rather than one
  # action outright, and keys on two facts — the address and the ip —
  # independently, so it cannot be declared as a single `rate_limit_guard`.
  #
  # Returns the trip, having already rendered the 429 for it, or nil when the
  # key is still within its limit — nil is also what a disabled limit
  # answers with, so a caller never has to ask which.
  def rate_limit_trip!(name, by:)
    config = RateLimits.fetch(name)
    return nil unless config.enabled?

    key = by.to_s
    cache_key = "rate-limit:#{name}:#{RateLimiting.windowed_key(key, config.window_seconds)}"
    count = RateLimits::STORE.increment(cache_key, 1, expires_in: config.window_seconds)
    return nil unless count && count > config.count

    trip = RateLimiting.trip(name, key, config.window_seconds)
    render_rate_limit_trip(trip)
    trip
  end

  # The ip a rate limit keys on: the socket's own peer unless the deployment
  # names a trusted proxy to read a forwarded-for header from instead.
  def rate_limit_client_ip
    ENV["TRUSTED_PROXIES"].present? ? request.remote_ip : request.remote_addr
  end

  private

  def render_rate_limit_trip(trip)
    response.set_header("Retry-After", trip.retry_after_seconds.to_s)
    Rails.logger.warn(
      event: "rate_limit.exceed", phase: "refused", msg: "refused: too many requests",
      data: {
        limit: trip.limit, key: redacted_rate_limit_key(trip.key),
        retry_after_seconds: trip.retry_after_seconds
      }
    )

    render_too_many_requests(trip)
  end

  # An address is the key `magic_link_request` checks by email, and
  # `docs/alignment.md` §2.1 keeps addresses out of `data` the same way it
  # keeps out cookie values, tokens, and card numbers. Every other limit
  # keys on an id or an ip, neither of which reads as one.
  def redacted_rate_limit_key(key)
    key.include?("@") ? ActiveSupport::ParameterFilter::FILTERED : key
  end

  def rate_limit_message(trip)
    minutes = [ (trip.retry_after_seconds / 60.0).ceil, 1 ].max

    "Too many requests — try again in #{minutes} #{"minute".pluralize(minutes)}."
  end

  # The plain page every limit answers with unless the action it guards
  # overrides this to re-render its own form instead. The template renders
  # inside whichever site's layout is already in effect for the controller
  # that tripped.
  def render_too_many_requests(trip)
    render "application/rate_limit_exceeded", status: :too_many_requests,
      locals: { message: rate_limit_message(trip) }
  end
end
