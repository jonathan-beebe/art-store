require "json"

# Every log record as one line of JSON on stdout. The line says when it
# happened, what it is about and how far along it is, and carries the request,
# the browser, the actor, and the unit of work it belongs to from `Current`,
# so the lines of one action read together.
class JsonLogFormatter < ActiveSupport::Logger::SimpleFormatter
  # What a record that did not come from a story is filed as. Rails and the
  # gems under it write prose; it keeps a line of its own rather than being
  # dropped or breaking the one-object-per-line rule.
  PROSE = { event: "app.log", phase: "did" }.freeze

  def call(severity, timestamp, _progname, message)
    "#{payload(severity, timestamp, message).to_json}\n"
  end

  private

  def payload(severity, timestamp, message)
    line = message.is_a?(Hash) ? message : PROSE.merge(msg: message.to_s)

    {
      ts: timestamp.utc.iso8601(3),
      level: severity.to_s.downcase,
      event: line[:event],
      phase: line[:phase],
      msg: line[:msg],
      request_id: Current.request_id,
      session_id: Current.session_id,
      actor_type: Current.actor_type,
      actor_id: Current.actor_id,
      txn_id: Current.txn_id,
      duration_ms: line[:duration_ms],
      data: line[:data],
      error: line[:error]
    }.compact
  end
end
